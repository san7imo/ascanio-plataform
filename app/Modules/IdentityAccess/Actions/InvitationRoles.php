<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\TeamInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationRoles
{
    public function __construct(private CheckDelegation $delegation) {}

    public function snapshot(Tenant $tenant, Role $role): array
    {
        $permissions = DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.tenant_id', $tenant->id)->where('role_permissions.role_id', $role->id)
            ->orderBy('permissions.code')->get(['permissions.code', 'role_permissions.is_delegable'])
            ->map(fn ($row) => ['code' => $row->code, 'delegable' => (bool) $row->is_delegable])->all();

        return ['id' => $role->id, 'name' => $role->name, 'permissions' => $permissions];
    }

    public function attach(User $actor, Tenant $tenant, TeamInvitation $invitation, array $assignments): void
    {
        foreach ($assignments as $assignment) {
            $role = Role::query()->where('tenant_id', $tenant->id)->findOrFail($assignment['role_id']);
            abort_if($role->system_kind !== null, 403);
            $this->delegation->role($actor, $tenant, $role->id);
            abort_unless(in_array($assignment['scope'], ['tenant', 'assigned_resources'], true), 422);
            DB::table('team_invitation_roles')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'invitation_id' => $invitation->id,
                'role_id' => $role->id, 'access_scope' => $assignment['scope'],
                'snapshot' => json_encode($this->snapshot($tenant, $role), JSON_THROW_ON_ERROR),
            ]);
        }
    }

    public function validated(User $actor, Tenant $tenant, TeamInvitation $invitation): array
    {
        $rows = DB::table('team_invitation_roles')->where('tenant_id', $tenant->id)->where('invitation_id', $invitation->id)->orderBy('role_id')->get();
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['invitation' => 'La invitación no tiene roles disponibles. Solicita una nueva.']);
        }
        foreach ($rows as $row) {
            $role = $row->role_id ? Role::query()->where('tenant_id', $tenant->id)->find($row->role_id) : null;
            $snapshot = json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR);
            if (! $role || $role->system_kind !== null || $snapshot['permissions'] != $this->snapshot($tenant, $role)['permissions']) {
                throw ValidationException::withMessages(['invitation' => 'Los roles cambiaron. Solicita una nueva invitación.']);
            }
            $this->delegation->role($actor, $tenant, $role->id);
        }

        return $rows->all();
    }
}
