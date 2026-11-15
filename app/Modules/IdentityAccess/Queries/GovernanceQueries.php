<?php

namespace App\Modules\IdentityAccess\Queries;

use App\Models\User;
use App\Modules\IdentityAccess\CapabilityCatalog;
use App\Modules\IdentityAccess\Models\Membership;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GovernanceQueries
{
    public function __construct(private WorkspaceAccess $access) {}

    public function roles(User $actor, Tenant $tenant, string $search = ''): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('manageRoles', $tenant);

        return Role::query()->where('tenant_id', $tenant->id)
            ->when($search !== '', fn ($query) => $query->where('name', 'ilike', '%'.mb_substr($search, 0, 120).'%'))
            ->orderBy('name')->orderBy('id')->paginate(10)->withQueryString()
            ->through(function ($role) use ($actor, $tenant) {
                $permissions = $this->permissions($tenant, $role->id);

                return [
                    'id' => $role->id, 'name' => $role->name, 'protected' => $role->system_kind !== null,
                    'permissions' => $permissions,
                    'can_edit' => $role->system_kind === null && $this->canDelegateAll($actor, $tenant, $permissions),
                ];
            });
    }

    public function capabilities(User $actor, Tenant $tenant): array
    {
        Gate::forUser($actor)->authorize('manageRoles', $tenant);

        return array_map(fn ($code) => ['code' => $code, 'label' => CapabilityCatalog::LABELS[$code]], $this->access->delegable($actor, $tenant));
    }

    public function assignableRoles(User $actor, Tenant $tenant): array
    {
        Gate::forUser($actor)->authorize('assignRoles', $tenant);

        return Role::query()->where('tenant_id', $tenant->id)->whereNull('system_kind')->orderBy('name')->orderBy('id')->get()
            ->filter(fn ($role) => $this->canDelegateAll($actor, $tenant, $this->permissions($tenant, $role->id)))
            ->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values()->all();
    }

    public function assignments(User $actor, Tenant $tenant, string $membershipId): array
    {
        Gate::forUser($actor)->authorize('assignRoles', $tenant);

        return DB::table('membership_roles')->join('roles', function ($join) {
            $join->on('roles.id', '=', 'membership_roles.role_id')->on('roles.tenant_id', '=', 'membership_roles.tenant_id');
        })->where('membership_roles.tenant_id', $tenant->id)->where('membership_roles.membership_id', $membershipId)
            ->orderBy('roles.id')->get(['roles.id as role_id', 'roles.name', 'membership_roles.access_scope as scope'])
            ->map(fn ($row) => (array) $row)->all();
    }

    private function permissions(Tenant $tenant, string $roleId): array
    {
        return DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.tenant_id', $tenant->id)->where('role_permissions.role_id', $roleId)
            ->orderBy('permissions.code')->get(['permissions.code', 'role_permissions.is_delegable'])
            ->map(fn ($row) => ['code' => $row->code, 'label' => CapabilityCatalog::LABELS[$row->code] ?? 'Capacidad no disponible', 'delegable' => (bool) $row->is_delegable])->all();
    }

    private function canDelegateAll(User $actor, Tenant $tenant, array $permissions): bool
    {
        return $this->access->canDelegateCodes($actor, $tenant, array_column($permissions, 'code'));
    }

    public function canAssign(User $actor, Tenant $tenant, Membership $member): bool
    {
        return Gate::forUser($actor)->allows('assignRoles', $tenant) && ! $member->is_owner
            && $member->status === 'active' && $this->canDelegateMemberRoles($actor, $tenant, $member);
    }

    public function canChangeStatus(User $actor, Tenant $tenant, Membership $member): bool
    {
        return Gate::forUser($actor)->allows('revokeMember', $tenant)
            && ($member->is_owner ? $this->access->isOwner($actor, $tenant) : $this->canDelegateMemberRoles($actor, $tenant, $member));
    }

    private function canDelegateMemberRoles(User $actor, Tenant $tenant, Membership $member): bool
    {
        $codes = DB::table('membership_roles')->join('role_permissions', function ($join) {
            $join->on('role_permissions.role_id', '=', 'membership_roles.role_id')
                ->on('role_permissions.tenant_id', '=', 'membership_roles.tenant_id');
        })->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('membership_roles.tenant_id', $tenant->id)->where('membership_roles.membership_id', $member->id)->pluck('permissions.code')->all();

        return $this->access->canDelegateCodes($actor, $tenant, $codes);
    }
}
