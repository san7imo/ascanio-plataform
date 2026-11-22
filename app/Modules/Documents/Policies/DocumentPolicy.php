<?php

namespace App\Modules\Documents\Policies;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\WorkspaceAccess;

class DocumentPolicy
{
    public function __construct(private WorkspaceAccess $access) {}

    public function viewAny(User $actor, Tenant $tenant): bool
    {
        return $this->access->allows($actor, $tenant, 'documents.view');
    }

    public function view(User $actor, Document $document, Tenant $tenant): bool
    {
        return $document->tenant_id === $tenant->id && $this->viewAny($actor, $tenant);
    }

    public function create(User $actor, Tenant $tenant): bool
    {
        return $this->viewAny($actor, $tenant) && $this->access->allows($actor, $tenant, 'documents.upload');
    }

    public function upload(User $actor, Document $document, Tenant $tenant): bool
    {
        return $this->view($actor, $document, $tenant) && $this->access->allows($actor, $tenant, 'documents.upload');
    }

    public function download(User $actor, Document $document, Tenant $tenant): bool
    {
        return $this->view($actor, $document, $tenant) && $this->access->allows($actor, $tenant, 'documents.download');
    }
}
