<?php

namespace App\Modules\IdentityAccess\Queries;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Membership;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

final class WorkspaceAccess
{
    public function __construct(private TenantContext $context) {}

    public function allows(User $user, Tenant $tenant, string $capability): bool
    {
        if (! $this->context->matches($user, $tenant)) {
            return false;
        }

        return $this->capableMemberships($user, $capability)->where('tenant_memberships.tenant_id', $tenant->id)->exists();
    }

    public function capableMemberships(User $user, string $capability): Builder
    {
        return Membership::query()
            ->join('users', 'users.id', '=', 'tenant_memberships.user_id')
            ->join('tenants', 'tenants.id', '=', 'tenant_memberships.tenant_id')
            ->join('membership_roles', function (JoinClause $join) {
                $join->on('membership_roles.membership_id', '=', 'tenant_memberships.id')
                    ->on('membership_roles.tenant_id', '=', 'tenant_memberships.tenant_id');
            })
            ->join('role_permissions', function (JoinClause $join) {
                $join->on('role_permissions.role_id', '=', 'membership_roles.role_id')
                    ->on('role_permissions.tenant_id', '=', 'membership_roles.tenant_id');
            })
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('tenant_memberships.user_id', $user->id)
            ->where('tenant_memberships.status', 'active')
            ->where('users.status', 'active')->whereNotNull('users.email_verified_at')
            ->where('tenants.status', 'active')
            ->where('membership_roles.access_scope', 'tenant')
            ->where('permissions.code', $capability);
    }
}
