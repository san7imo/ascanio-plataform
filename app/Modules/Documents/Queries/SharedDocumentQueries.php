<?php

namespace App\Modules\Documents\Queries;

use App\Models\User;
use App\Modules\Documents\Models\DocumentVersion;
use App\Modules\IdentityAccess\Models\ResourceGrant;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\DocumentGrantAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class SharedDocumentQueries
{
    public function __construct(private DocumentGrantAccess $access) {}

    public function listing(User $actor): LengthAwarePaginator
    {
        return $this->access->query($actor)->orderBy('resource_grants.id')->paginate(10)->through(fn ($grant) => $this->projection($actor, $grant->id));
    }

    public function projection(User $actor, string $id): array
    {
        $grant = $this->access->find($actor, $id);
        Gate::forUser($actor)->authorize('view', $grant);
        $tenant = Tenant::query()->findOrFail($grant->tenant_id);
        $version = DocumentVersion::query()->where('tenant_id', $grant->tenant_id)->findOrFail($grant->document_version_id);

        return $this->version($grant, $tenant, $version);
    }

    public function version(ResourceGrant $grant, Tenant $tenant, DocumentVersion $version): array
    {
        return [
            'id' => $grant->id, 'workspace' => $tenant->name, 'timezone' => $tenant->timezone,
            'number' => $version->number, 'filename' => $version->filename, 'mime_type' => $version->mime_type,
            'size_bytes' => $version->size_bytes, 'sha256' => $version->sha256, 'scan_status' => $version->scan_status,
            'created_at' => $version->created_at->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
            'expires_at' => $grant->expires_at->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
            'projection' => $grant->projection, 'can_download' => $grant->projection === 'file' && $version->scan_status === 'clean',
        ];
    }
}
