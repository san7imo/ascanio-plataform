<?php

namespace App\Modules\Documents\Queries;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentVersion;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class DocumentQueries
{
    public function listing(User $actor, Tenant $tenant, string $search = ''): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', [Document::class, $tenant]);

        return Document::query()->where('tenant_id', $tenant->id)
            ->when($search !== '', fn ($query) => $query->where('title', 'ilike', '%'.mb_substr($search, 0, 120).'%'))
            ->select(['id', 'title', 'version_count'])->orderBy('title')->orderBy('id')->paginate(10)->withQueryString();
    }

    public function find(User $actor, Tenant $tenant, string $id): Document
    {
        Gate::forUser($actor)->authorize('viewAny', [Document::class, $tenant]);
        $document = Document::query()->where('tenant_id', $tenant->id)->findOrFail($id);
        Gate::forUser($actor)->authorize('view', [$document, $tenant]);

        return $document;
    }

    public function versions(User $actor, Tenant $tenant, Document $document): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('view', [$document, $tenant]);
        $download = Gate::forUser($actor)->allows('download', [$document, $tenant]);
        $upload = Gate::forUser($actor)->allows('upload', [$document, $tenant]);

        return DocumentVersion::query()->where('tenant_id', $tenant->id)->where('document_id', $document->id)
            ->select(['id', 'number', 'filename', 'mime_type', 'size_bytes', 'sha256', 'scan_status', 'created_at'])
            ->orderByDesc('number')->paginate(10)->withQueryString()->through(fn ($version) => [
                'id' => $version->id, 'number' => $version->number, 'filename' => $version->filename,
                'mime_type' => $version->mime_type, 'size_bytes' => $version->size_bytes, 'sha256' => $version->sha256,
                'scan_status' => $version->scan_status, 'created_at' => $version->created_at->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
                'can_download' => $download && $version->scan_status === 'clean',
                'can_retry' => $upload && $version->scan_status === 'scan_failed',
            ]);
    }
}
