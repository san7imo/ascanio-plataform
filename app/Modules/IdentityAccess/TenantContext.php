<?php

namespace App\Modules\IdentityAccess;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Membership;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;

final class TenantContext
{
    private ?Tenant $tenant = null;

    private ?Membership $membership = null;

    public function establish(Tenant $tenant, Membership $membership): void
    {
        if ($membership->tenant_id !== $tenant->id || $membership->status !== 'active' || $tenant->status !== 'active') {
            throw new AuthorizationException;
        }

        $this->tenant = $tenant;
        $this->membership = $membership;
    }

    public function matches(User $user, Tenant $tenant): bool
    {
        return $this->tenant?->id === $tenant->id && $this->membership?->user_id === $user->id;
    }

    public function tenant(): Tenant
    {
        return $this->tenant ?? throw new AuthorizationException('Selecciona un espacio autorizado.');
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->membership = null;
    }
}
