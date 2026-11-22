<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const CAPABILITIES = ['documents.view', 'documents.upload', 'documents.download'];

    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('title', 160);
            $table->string('description', 1000)->nullable();
            $table->unsignedInteger('version_count')->default(0);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'title', 'id']);
        });
        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('document_id');
            $table->unsignedInteger('number');
            $table->foreignUuid('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('upload_key_hash', 64);
            $table->string('payload_hash', 64);
            $table->string('filename', 180);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->string('storage_path', 512);
            $table->string('scan_status', 20)->default('quarantined');
            $table->unsignedInteger('scan_generation')->default(1);
            $table->foreignUuid('scan_requested_by')->constrained('users')->restrictOnDelete();
            $table->uuid('source_membership_id');
            $table->unsignedBigInteger('source_access_version');
            $table->string('failure_code', 40)->nullable();
            $table->timestampTz('scanned_at')->nullable();
            $table->timestampsTz();
            $table->foreign(['tenant_id', 'document_id'])->references(['tenant_id', 'id'])->on('documents')->restrictOnDelete();
            $table->foreign(['tenant_id', 'source_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'document_id', 'number']);
            $table->unique(['tenant_id', 'uploaded_by', 'upload_key_hash'], 'document_upload_idempotency');
        });
        DB::statement('ALTER TABLE documents ADD CONSTRAINT document_version_count CHECK (version_count >= 0)');
        DB::statement("ALTER TABLE document_versions ADD CONSTRAINT document_version_state CHECK (number > 0 AND size_bytes > 0 AND scan_generation > 0 AND scan_status IN ('quarantined','clean','infected','scan_failed','blocked') AND (scan_status <> 'clean' OR (scanned_at IS NOT NULL AND failure_code IS NULL)))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_document_version() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP <> 'UPDATE' THEN
                    RAISE EXCEPTION 'Document version history must be preserved' USING ERRCODE = '23514';
                END IF;
                IF ROW(NEW.id, NEW.tenant_id, NEW.document_id, NEW.number, NEW.uploaded_by, NEW.upload_key_hash, NEW.payload_hash, NEW.filename, NEW.mime_type, NEW.size_bytes, NEW.sha256, NEW.storage_path, NEW.created_at)
                    IS DISTINCT FROM ROW(OLD.id, OLD.tenant_id, OLD.document_id, OLD.number, OLD.uploaded_by, OLD.upload_key_hash, OLD.payload_hash, OLD.filename, OLD.mime_type, OLD.size_bytes, OLD.sha256, OLD.storage_path, OLD.created_at) THEN
                    RAISE EXCEPTION 'Document version payload is immutable' USING ERRCODE = '23514';
                END IF;
                IF OLD.scan_status IN ('infected','blocked') OR (OLD.scan_status = 'clean' AND NOT (NEW.scan_status = 'blocked' AND NEW.failure_code IN ('file_missing','integrity_mismatch'))) THEN
                    RAISE EXCEPTION 'Terminal scan state cannot be reopened' USING ERRCODE = '23514';
                END IF;
                IF NEW.scan_generation <> OLD.scan_generation AND NOT (OLD.scan_status = 'scan_failed' AND NEW.scan_status = 'quarantined' AND NEW.scan_generation = OLD.scan_generation + 1) THEN
                    RAISE EXCEPTION 'Invalid scan retry generation' USING ERRCODE = '23514';
                END IF;
                IF ROW(NEW.scan_requested_by, NEW.source_membership_id, NEW.source_access_version) IS DISTINCT FROM ROW(OLD.scan_requested_by, OLD.source_membership_id, OLD.source_access_version) AND NEW.scan_generation = OLD.scan_generation THEN
                    RAISE EXCEPTION 'Scan authority requires an explicit retry' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER document_version_immutable BEFORE UPDATE OR DELETE ON document_versions FOR EACH ROW EXECUTE FUNCTION protect_document_version();
            CREATE TRIGGER document_version_no_truncate BEFORE TRUNCATE ON document_versions FOR EACH STATEMENT EXECUTE FUNCTION protect_document_version();
            SQL);
        foreach (self::CAPABILITIES as $code) {
            DB::table('permissions')->insertOrIgnore(['id' => (string) Str::uuid(), 'code' => $code]);
        }
        $ids = DB::table('permissions')->whereIn('code', self::CAPABILITIES)->pluck('id');
        // Narrow schema upgrade of the protected initial bundle; pause writers.
        DB::table('roles')->where('system_kind', 'owner')->orderBy('id')->each(function ($role) use ($ids) {
            foreach ($ids as $id) {
                DB::table('role_permissions')->insert(['tenant_id' => $role->tenant_id, 'role_id' => $role->id, 'permission_id' => $id, 'is_delegable' => true]);
            }
            $members = DB::table('membership_roles')->where('tenant_id', $role->tenant_id)->where('role_id', $role->id)->pluck('membership_id');
            DB::table('tenant_memberships')->where('tenant_id', $role->tenant_id)->whereIn('id', $members)->increment('access_version');
            DB::table('audit_events')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $role->tenant_id, 'actor_type' => 'migration', 'actor_id' => null,
                'action' => 'access.documents.upgraded', 'resource_type' => 'role', 'resource_id' => $role->id, 'occurred_at' => now(),
                'changes' => json_encode(['source' => '2026_11_21_000001', 'capabilities' => self::CAPABILITIES], JSON_THROW_ON_ERROR),
            ]);
        });
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('code', self::CAPABILITIES)->pluck('id');
        $assigned = DB::table('role_permissions')->join('roles', 'roles.id', '=', 'role_permissions.role_id')->whereIn('permission_id', $ids)->whereNull('roles.system_kind')->exists();
        $proposed = collect(self::CAPABILITIES)->contains(fn ($code) => DB::table('team_invitation_roles')->whereJsonContains('snapshot->permissions', [['code' => $code]])->exists());
        if (DB::table('documents')->exists() || DB::table('audit_events')->where('action', 'access.documents.upgraded')->exists() || $assigned || $proposed) {
            throw new RuntimeException('Document history must be preserved; use a forward migration.');
        }
        Schema::dropIfExists('document_versions');
        DB::statement('DROP FUNCTION IF EXISTS protect_document_version()');
        Schema::dropIfExists('documents');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
