<?php

namespace App\Modules\IdentityAccess\Policies;

use App\Models\User;
use App\Modules\IdentityAccess\Models\ResourceGrant;
use App\Modules\IdentityAccess\Queries\DocumentGrantAccess;

class ResourceGrantPolicy
{
    public function __construct(private DocumentGrantAccess $access) {}

    public function view(User $actor, ResourceGrant $grant): bool
    {
        return $this->access->query($actor)->where('resource_grants.id', $grant->id)->where('resource_grants.tenant_id', $grant->tenant_id)->exists();
    }

    public function download(User $actor, ResourceGrant $grant): bool
    {
        return $this->access->query($actor)->where('resource_grants.id', $grant->id)->where('resource_grants.tenant_id', $grant->tenant_id)
            ->where('resource_grants.capability', 'documents.download')->where('resource_grants.projection', 'file')->exists();
    }
}
