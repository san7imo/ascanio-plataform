<?php

namespace App\Modules\Parties\Policies;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\WorkspaceAccess;
use App\Modules\Parties\Models\TenantParty;

class TenantPartyPolicy
{
    public function __construct(private WorkspaceAccess $access) {}

    public function viewAny(User $actor, Tenant $tenant): bool
    {
        return $this->access->allows($actor, $tenant, 'parties.view');
    }

    public function view(User $actor, TenantParty $party, Tenant $tenant): bool
    {
        return $party->tenant_id === $tenant->id && $this->viewAny($actor, $tenant);
    }

    public function viewPrivate(User $actor, TenantParty $party, Tenant $tenant): bool
    {
        return $this->view($actor, $party, $tenant) && $this->access->allows($actor, $tenant, 'parties.view_private');
    }

    public function create(User $actor, Tenant $tenant): bool
    {
        return $this->viewAny($actor, $tenant) && $this->access->allows($actor, $tenant, 'parties.view_private') && $this->access->allows($actor, $tenant, 'parties.create');
    }

    public function update(User $actor, TenantParty $party, Tenant $tenant): bool
    {
        return $this->viewPrivate($actor, $party, $tenant) && $this->access->allows($actor, $tenant, 'parties.update');
    }
}
