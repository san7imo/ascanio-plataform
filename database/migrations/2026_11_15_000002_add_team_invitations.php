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
        Schema::table('tenant_memberships', fn (Blueprint $table) => $table->unsignedBigInteger('access_version')->default(0));
        Schema::create('team_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('inviter_id')->constrained('users')->restrictOnDelete();
            $table->uuid('source_membership_id');
            $table->unsignedBigInteger('source_access_version');
            $table->foreign(['tenant_id', 'source_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->string('recipient_email', 254);
            $table->string('purpose', 30)->default('team_membership');
            $table->string('token_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->timestampTz('expires_at');
            $table->foreignUuid('accepted_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->unsignedInteger('delivery_version')->default(1);
            $table->string('delivery_status', 20)->default('pending');
            $table->unsignedInteger('delivery_attempts')->default(0);
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->string('failure_code', 40)->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status', 'created_at']);
        });
        Schema::create('team_invitation_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('invitation_id');
            $table->uuid('role_id')->nullable();
            $table->string('access_scope', 30);
            $table->jsonb('snapshot');
            $table->foreign(['tenant_id', 'invitation_id'])->references(['tenant_id', 'id'])->on('team_invitations')->restrictOnDelete();
            $table->foreign(['tenant_id', 'role_id'])->references(['tenant_id', 'id'])->on('roles')->restrictOnDelete();
            $table->unique(['tenant_id', 'invitation_id', 'role_id']);
        });
        DB::statement("CREATE UNIQUE INDEX team_pending_recipient ON team_invitations (tenant_id, recipient_email) WHERE status = 'pending'");
        DB::statement("ALTER TABLE team_invitations ADD CONSTRAINT team_invitation_state CHECK (purpose = 'team_membership' AND status IN ('pending','accepted','revoked','expired') AND ((status = 'accepted' AND accepted_at IS NOT NULL AND accepted_by_id IS NOT NULL) OR (status <> 'accepted' AND accepted_at IS NULL AND accepted_by_id IS NULL)) AND ((status = 'revoked' AND revoked_at IS NOT NULL) OR (status <> 'revoked' AND revoked_at IS NULL)))");
        DB::statement("ALTER TABLE team_invitations ADD CONSTRAINT team_delivery_state CHECK (delivery_version > 0 AND delivery_attempts >= 0 AND delivery_status IN ('pending','sent','simulated','failed','blocked'))");
        DB::statement("ALTER TABLE team_invitation_roles ADD CONSTRAINT team_invitation_scope CHECK (access_scope IN ('tenant','assigned_resources'))");
        $permission = (string) Str::uuid();
        DB::table('permissions')->insertOrIgnore(['id' => $permission, 'code' => 'tenant.members.invite']);
        $permission = DB::table('permissions')->where('code', 'tenant.members.invite')->value('id');
        // Narrow global schema upgrade, not an application directory or bypass.
        DB::table('roles')->where('system_kind', 'owner')->orderBy('id')->each(function ($role) use ($permission) {
            DB::table('role_permissions')->insert(['tenant_id' => $role->tenant_id, 'role_id' => $role->id, 'permission_id' => $permission, 'is_delegable' => true]);
            DB::table('audit_events')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $role->tenant_id, 'actor_type' => 'migration', 'actor_id' => null,
                'action' => 'access.invitations.upgraded', 'resource_type' => 'role', 'resource_id' => $role->id, 'occurred_at' => now(),
                'changes' => json_encode(['source' => '2026_11_15_000002', 'capability' => 'tenant.members.invite'], JSON_THROW_ON_ERROR),
            ]);
        });
    }

    public function down(): void
    {
        if (DB::table('team_invitations')->exists()) {
            throw new RuntimeException('Invitation history must be preserved; use a forward migration.');
        }
        Schema::dropIfExists('team_invitation_roles');
        Schema::dropIfExists('team_invitations');
        Schema::table('tenant_memberships', fn (Blueprint $table) => $table->dropColumn('access_version'));
        $id = DB::table('permissions')->where('code', 'tenant.members.invite')->value('id');
        DB::table('role_permissions')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();
    }
};
