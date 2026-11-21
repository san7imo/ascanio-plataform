<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const CAPABILITIES = ['parties.view', 'parties.view_private', 'parties.create', 'parties.update'];

    public function up(): void
    {
        Schema::create('business_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
        });
        foreach (['label' => 'Sello discográfico', 'publishing' => 'Edición musical', 'management' => 'Gestión de artistas', 'performance' => 'Interpretación', 'production' => 'Producción musical', 'writing' => 'Composición y escritura', 'dj_services' => 'Servicios de DJ', 'other' => 'Otra actividad'] as $code => $name) {
            DB::table('business_types')->insert(['id' => (string) Str::uuid(), 'code' => $code, 'name' => $name]);
        }
        Schema::create('tenant_parties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('party_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('display_name', 160);
            $table->string('legal_name', 160)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 20)->default('manual');
            $table->string('source_note', 500)->nullable();
            $table->string('verification_status', 20)->default('provisional');
            $table->unsignedInteger('version')->default(1);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'display_name', 'id']);
        });
        Schema::create('tenant_party_business_types', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('tenant_party_id');
            $table->foreignUuid('business_type_id')->constrained('business_types')->restrictOnDelete();
            $table->foreign(['tenant_id', 'tenant_party_id'])->references(['tenant_id', 'id'])->on('tenant_parties')->restrictOnDelete();
            $table->primary(['tenant_id', 'tenant_party_id', 'business_type_id']);
        });
        Schema::create('party_identifiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('tenant_party_id');
            $table->string('scheme', 20);
            $table->string('value', 120);
            $table->string('issuer', 160)->nullable();
            $table->foreign(['tenant_id', 'tenant_party_id'])->references(['tenant_id', 'id'])->on('tenant_parties')->restrictOnDelete();
            $table->unique(['tenant_id', 'tenant_party_id', 'scheme', 'value']);
        });
        Schema::create('tenant_party_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('tenant_party_id');
            $table->unsignedInteger('version');
            $table->jsonb('snapshot');
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('recorded_at');
            $table->foreign(['tenant_id', 'tenant_party_id'])->references(['tenant_id', 'id'])->on('tenant_parties')->restrictOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'tenant_party_id', 'version']);
        });
        DB::statement("ALTER TABLE tenant_parties ADD CONSTRAINT tenant_party_provisional CHECK (kind IN ('person','organization') AND verification_status = 'provisional' AND party_id IS NULL AND source = 'manual' AND version > 0 AND length(trim(display_name)) > 0)");
        DB::statement("ALTER TABLE party_identifiers ADD CONSTRAINT party_identifier_declared CHECK (scheme IN ('ipi','isni','other') AND length(trim(value)) > 0)");
        DB::statement('ALTER TABLE tenant_party_revisions ADD CONSTRAINT party_revision_version CHECK (version > 0)');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_party_revision_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Party revisions are immutable' USING ERRCODE = '23514';
            END;
            $$;
            CREATE TRIGGER party_revision_immutable BEFORE UPDATE OR DELETE ON tenant_party_revisions
                FOR EACH ROW EXECUTE FUNCTION reject_party_revision_mutation();
            CREATE TRIGGER party_revision_no_truncate BEFORE TRUNCATE ON tenant_party_revisions
                FOR EACH STATEMENT EXECUTE FUNCTION reject_party_revision_mutation();
            SQL);
        foreach (self::CAPABILITIES as $code) {
            DB::table('permissions')->insertOrIgnore(['id' => (string) Str::uuid(), 'code' => $code]);
        }
        $permissions = DB::table('permissions')->whereIn('code', self::CAPABILITIES)->pluck('id');
        // Bounded migration backfill of the protected initial bundle, never an
        // application-wide directory. Pause writes/workers during this upgrade.
        DB::table('roles')->where('system_kind', 'owner')->orderBy('id')->each(function ($role) use ($permissions) {
            foreach ($permissions as $permission) {
                DB::table('role_permissions')->insert(['tenant_id' => $role->tenant_id, 'role_id' => $role->id, 'permission_id' => $permission, 'is_delegable' => true]);
            }
            $members = DB::table('membership_roles')->where('tenant_id', $role->tenant_id)->where('role_id', $role->id)->pluck('membership_id');
            DB::table('tenant_memberships')->where('tenant_id', $role->tenant_id)->whereIn('id', $members)->increment('access_version');
            DB::table('audit_events')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $role->tenant_id, 'actor_type' => 'migration', 'actor_id' => null,
                'action' => 'access.parties.upgraded', 'resource_type' => 'role', 'resource_id' => $role->id, 'occurred_at' => now(),
                'changes' => json_encode(['source' => '2026_11_15_000003', 'capabilities' => self::CAPABILITIES], JSON_THROW_ON_ERROR),
            ]);
        });
    }

    public function down(): void
    {
        $permissions = DB::table('permissions')->whereIn('code', self::CAPABILITIES)->pluck('id');
        $assigned = DB::table('role_permissions')->join('roles', 'roles.id', '=', 'role_permissions.role_id')->whereIn('permission_id', $permissions)->whereNull('roles.system_kind')->exists();
        $proposed = collect(self::CAPABILITIES)->contains(fn ($code) => DB::table('team_invitation_roles')->whereJsonContains('snapshot->permissions', [['code' => $code]])->exists());
        if (DB::table('tenant_parties')->exists() || DB::table('audit_events')->where('action', 'access.parties.upgraded')->exists() || $assigned || $proposed) {
            throw new RuntimeException('Party history must be preserved; use a forward migration.');
        }
        Schema::dropIfExists('tenant_party_revisions');
        DB::statement('DROP FUNCTION IF EXISTS reject_party_revision_mutation()');
        Schema::dropIfExists('party_identifiers');
        Schema::dropIfExists('tenant_party_business_types');
        Schema::dropIfExists('tenant_parties');
        Schema::dropIfExists('business_types');
        DB::table('role_permissions')->whereIn('permission_id', $permissions)->delete();
        DB::table('permissions')->whereIn('id', $permissions)->delete();
    }
};
