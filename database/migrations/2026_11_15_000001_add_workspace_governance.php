<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const CAPABILITIES = ['tenant.view', 'tenant.settings.update', 'tenant.members.view', 'tenant.members.revoke', 'tenant.roles.manage', 'tenant.members.assign_roles', 'tenant.ownership.transfer'];

    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('system_kind', 20)->nullable();
            $table->unique(['tenant_id', 'system_kind']);
        });
        Schema::table('role_permissions', fn (Blueprint $table) => $table->boolean('is_delegable')->default(false));
        Schema::table('users', fn (Blueprint $table) => $table->bigInteger('last_sensitive_totp_step')->nullable());
        Schema::table('audit_events', function (Blueprint $table) {
            $table->uuid('actor_id')->nullable()->change();
            $table->string('actor_type', 20)->default('user');
        });
        DB::statement("ALTER TABLE roles ADD CONSTRAINT role_system_kind CHECK (system_kind IS NULL OR system_kind = 'owner')");
        DB::statement("ALTER TABLE audit_events ADD CONSTRAINT audit_actor CHECK ((actor_type = 'user' AND actor_id IS NOT NULL) OR (actor_type = 'migration' AND actor_id IS NULL))");

        foreach (self::CAPABILITIES as $code) {
            DB::table('permissions')->insertOrIgnore(['id' => (string) Str::uuid(), 'code' => $code]);
        }

        // Deliberately global schema upgrade, never an application access path:
        // upgrade only the F1 bootstrap bundle, checked against ownership facts.
        DB::table('tenants')->orderBy('id')->each(function ($tenant) {
            $role = DB::table('roles')->where('tenant_id', $tenant->id)->where('code', 'owner')->first();

            if (! $role || DB::table('membership_roles as mr')->join('tenant_memberships as m', function ($join) {
                $join->on('m.id', '=', 'mr.membership_id')->on('m.tenant_id', '=', 'mr.tenant_id');
            })->where('mr.tenant_id', $tenant->id)->where('mr.role_id', $role->id)->where('m.is_owner', false)->exists()) {
                throw new RuntimeException('Review the F1 owner bundle mapping before upgrading governance.');
            }

            DB::table('roles')->where('tenant_id', $tenant->id)->where('id', $role->id)->update(['system_kind' => 'owner']);

            foreach (self::CAPABILITIES as $code) {
                $permission = DB::table('permissions')->where('code', $code)->value('id');
                DB::table('role_permissions')->updateOrInsert(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $permission], ['is_delegable' => $code !== 'tenant.ownership.transfer']);
            }

            foreach (DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->where('is_owner', true)->pluck('id') as $membership) {
                DB::table('membership_roles')->updateOrInsert(['tenant_id' => $tenant->id, 'membership_id' => $membership, 'role_id' => $role->id], ['access_scope' => 'tenant']);
            }

            DB::table('audit_events')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id,
                'actor_id' => null, 'actor_type' => 'migration', 'action' => 'access.foundation.upgraded',
                'resource_type' => 'role', 'resource_id' => $role->id, 'occurred_at' => now(),
                'changes' => json_encode(['source' => '2026_11_15_000001', 'capabilities' => self::CAPABILITIES], JSON_THROW_ON_ERROR),
            ]);
        });
    }

    public function down(): void
    {
        if (DB::table('audit_events')->where('actor_type', 'migration')->exists()) {
            throw new RuntimeException('Governance upgrade evidence must be preserved; use a forward migration instead of this rollback.');
        }

        $ids = DB::table('permissions')->whereIn('code', array_slice(self::CAPABILITIES, 4))->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        DB::statement('ALTER TABLE audit_events DROP CONSTRAINT audit_actor');
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropColumn('actor_type');
            $table->uuid('actor_id')->nullable(false)->change();
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('last_sensitive_totp_step'));
        Schema::table('role_permissions', fn (Blueprint $table) => $table->dropColumn('is_delegable'));
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn('system_kind'));
    }
};
