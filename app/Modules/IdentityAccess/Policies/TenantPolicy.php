<?php

namespace App\Modules\IdentityAccess\Policies;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\WorkspaceAccess;

class TenantPolicy
{
    public function __construct(private WorkspaceAccess $access) {}

    public function create(User $user): bool
    {
        return User::query()->whereKey($user->id)->where('status', 'active')->whereNotNull('email_verified_at')->exists();
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $this->access->allows($user, $tenant, 'tenant.view');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->access->allows($user, $tenant, 'tenant.settings.update');
    }

    public function viewMembers(User $user, Tenant $tenant): bool
    {
        return $this->access->allows($user, $tenant, 'tenant.members.view');
    }

    public function revokeMember(User $user, Tenant $tenant): bool
    {
        return $this->access->allows($user, $tenant, 'tenant.members.revoke');
    }
}
