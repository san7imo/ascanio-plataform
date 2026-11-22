<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('document_version_id');
            $table->string('purpose', 40)->default('document_version_access');
            $table->string('capability', 40);
            $table->string('projection', 20);
            $table->string('recipient_email', 254);
            $table->foreignUuid('inviter_id')->constrained('users')->restrictOnDelete();
            $table->uuid('source_membership_id');
            $table->unsignedBigInteger('source_access_version');
            $table->string('token_hash', 64);
            $table->timestampTz('expires_at');
            $table->string('status', 20)->default('pending');
            $table->foreignUuid('accepted_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('accepted_at')->nullable();
            $table->unsignedInteger('delivery_version')->default(1);
            $table->string('delivery_status', 20)->default('pending');
            $table->unsignedInteger('delivery_attempts')->default(0);
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->string('failure_code', 40)->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id', 'document_version_id'], 'resource_invitation_target');
            $table->foreign(['tenant_id', 'document_version_id'])->references(['tenant_id', 'id'])->on('document_versions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'source_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->index(['tenant_id', 'document_version_id', 'created_at']);
        });
        Schema::create('resource_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('document_version_id');
            $table->uuid('invitation_id')->unique();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('inviter_id')->constrained('users')->restrictOnDelete();
            $table->uuid('source_membership_id');
            $table->unsignedBigInteger('source_access_version');
            $table->string('capability', 40);
            $table->string('projection', 20);
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'document_version_id'])->references(['tenant_id', 'id'])->on('document_versions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'source_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'invitation_id', 'document_version_id'], 'resource_grant_invitation_target')->references(['tenant_id', 'id', 'document_version_id'])->on('resource_invitations')->restrictOnDelete();
            $table->index(['user_id', 'tenant_id', 'expires_at']);
        });
        foreach (['resource_invitations', 'resource_grants'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_one_target CHECK (num_nonnulls(document_version_id) = 1)");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_projection CHECK ((capability = 'documents.view' AND projection = 'metadata') OR (capability = 'documents.download' AND projection = 'file'))");
        }
        DB::statement("ALTER TABLE resource_invitations ADD CONSTRAINT resource_invitation_state CHECK (purpose = 'document_version_access' AND status IN ('pending','accepted','revoked') AND delivery_version > 0 AND delivery_attempts >= 0 AND delivery_status IN ('pending','sent','simulated','failed','blocked') AND ((status = 'accepted' AND accepted_at IS NOT NULL AND accepted_by_id IS NOT NULL) OR (status <> 'accepted' AND accepted_at IS NULL AND accepted_by_id IS NULL)))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_resource_access() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP <> 'UPDATE' THEN
                    RAISE EXCEPTION 'Resource access history must be preserved' USING ERRCODE = '23514';
                END IF;
                IF TG_TABLE_NAME = 'resource_grants' THEN
                    IF (to_jsonb(NEW) - ARRAY['revoked_at','updated_at']) IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['revoked_at','updated_at']) OR OLD.revoked_at IS NOT NULL THEN
                        RAISE EXCEPTION 'Grant target and provenance are immutable' USING ERRCODE = '23514';
                    END IF;
                ELSE
                    IF (to_jsonb(NEW) - ARRAY['status','accepted_by_id','accepted_at','token_hash','delivery_version','delivery_status','delivery_attempts','last_attempt_at','delivered_at','failure_code','updated_at']) IS DISTINCT FROM
                       (to_jsonb(OLD) - ARRAY['status','accepted_by_id','accepted_at','token_hash','delivery_version','delivery_status','delivery_attempts','last_attempt_at','delivered_at','failure_code','updated_at']) OR OLD.status <> 'pending' THEN
                        RAISE EXCEPTION 'Invitation target and provenance are immutable' USING ERRCODE = '23514';
                    END IF;
                    IF NEW.token_hash <> OLD.token_hash AND (NEW.delivery_version <> OLD.delivery_version + 1 OR NEW.status <> 'pending') THEN
                        RAISE EXCEPTION 'Token rotation requires a new delivery generation' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER resource_grant_history BEFORE UPDATE OR DELETE ON resource_grants FOR EACH ROW EXECUTE FUNCTION protect_resource_access();
            CREATE TRIGGER resource_grant_no_truncate BEFORE TRUNCATE ON resource_grants FOR EACH STATEMENT EXECUTE FUNCTION protect_resource_access();
            CREATE TRIGGER resource_invitation_history BEFORE UPDATE OR DELETE ON resource_invitations FOR EACH ROW EXECUTE FUNCTION protect_resource_access();
            CREATE TRIGGER resource_invitation_no_truncate BEFORE TRUNCATE ON resource_invitations FOR EACH STATEMENT EXECUTE FUNCTION protect_resource_access();
            SQL);
        $permission = (string) Str::uuid();
        DB::table('permissions')->insert(['id' => $permission, 'code' => 'documents.share']);
        // Bounded schema backfill of the protected bundle, with writers paused.
        DB::table('roles')->where('system_kind', 'owner')->orderBy('id')->each(function ($role) use ($permission) {
            DB::table('role_permissions')->insert(['tenant_id' => $role->tenant_id, 'role_id' => $role->id, 'permission_id' => $permission, 'is_delegable' => true]);
            $members = DB::table('membership_roles')->where('tenant_id', $role->tenant_id)->where('role_id', $role->id)->pluck('membership_id');
            DB::table('tenant_memberships')->where('tenant_id', $role->tenant_id)->whereIn('id', $members)->increment('access_version');
            DB::table('audit_events')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $role->tenant_id, 'actor_type' => 'migration', 'actor_id' => null,
                'action' => 'access.document_sharing.upgraded', 'resource_type' => 'role', 'resource_id' => $role->id, 'occurred_at' => now(),
                'changes' => json_encode(['source' => '2026_11_22_000001', 'capabilities' => ['documents.share']], JSON_THROW_ON_ERROR),
            ]);
        });
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('code', 'documents.share')->value('id');
        if (DB::table('resource_invitations')->exists() || DB::table('resource_grants')->exists()
            || DB::table('audit_events')->where('action', 'access.document_sharing.upgraded')->exists()
            || DB::table('role_permissions')->join('roles', 'roles.id', '=', 'role_permissions.role_id')->where('permission_id', $permission)->whereNull('roles.system_kind')->exists()
            || DB::table('team_invitation_roles')->whereJsonContains('snapshot->permissions', [['code' => 'documents.share']])->exists()) {
            throw new RuntimeException('Resource access history must be preserved; use a forward migration.');
        }
        Schema::drop('resource_grants');
        Schema::drop('resource_invitations');
        DB::statement('DROP FUNCTION protect_resource_access()');
        DB::table('role_permissions')->where('permission_id', $permission)->delete();
        DB::table('permissions')->where('id', $permission)->delete();
    }
};
