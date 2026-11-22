<?php

namespace App\Modules\Documents\Actions;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentVersion;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RetryDocumentScan
{
    public function __construct(private QueueDocumentScan $queue, private RecordAudit $audit) {}

    public function execute(User $actor, Tenant $tenant, string $documentId, string $versionId): void
    {
        DB::transaction(function () use ($actor, $tenant, $documentId, $versionId) {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            Gate::forUser($actor)->authorize('create', [Document::class, $tenant]);
            $document = Document::query()->where('tenant_id', $tenant->id)->findOrFail($documentId);
            Gate::forUser($actor)->authorize('upload', [$document, $tenant]);
            $version = DocumentVersion::query()->where('tenant_id', $tenant->id)->where('document_id', $document->id)->lockForUpdate()->findOrFail($versionId);
            if ($version->scan_status !== 'scan_failed') {
                throw ValidationException::withMessages(['scan' => 'Solo se puede reintentar un análisis fallido. Una versión bloqueada o rechazada necesita una versión nueva.']);
            }
            $member = $tenant->memberships()->where('user_id', $actor->id)->sole();
            $version->update([
                'scan_status' => 'quarantined', 'failure_code' => null, 'scan_generation' => $version->scan_generation + 1,
                'scan_requested_by' => $actor->id, 'source_membership_id' => $member->id, 'source_access_version' => $member->access_version,
            ]);
            $this->audit->execute($actor, $tenant, 'document.scan_retried', 'document_version', $version->id, ['generation' => $version->scan_generation]);
            $this->queue->execute($version);
        }, 3);
    }
}
