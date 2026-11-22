<?php

namespace App\Modules\Documents\Actions;

use App\Models\User;
use App\Modules\Documents\Data\DocumentFileUnavailable;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentVersion;
use App\Modules\Documents\Storage\PrivateDocumentStore;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadDocumentVersion
{
    public function __construct(private PrivateDocumentStore $store, private RecordAudit $audit) {}

    public function execute(User $actor, Tenant $tenant, string $documentId, string $versionId): StreamedResponse
    {
        $stream = null;
        try {
            $version = DB::transaction(function () use ($actor, $tenant, $documentId, $versionId, &$stream) {
                $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
                $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
                Gate::forUser($actor)->authorize('viewAny', [Document::class, $tenant]);
                $document = Document::query()->where('tenant_id', $tenant->id)->findOrFail($documentId);
                Gate::forUser($actor)->authorize('download', [$document, $tenant]);
                $version = DocumentVersion::query()->where('tenant_id', $tenant->id)->where('document_id', $document->id)->lockForUpdate()->findOrFail($versionId);
                if ($version->scan_status !== 'clean') {
                    abort(409, 'Esta versión todavía no está disponible para descarga.');
                }
                try {
                    $stream = $this->store->verifiedStream($version);
                } catch (DocumentFileUnavailable $exception) {
                    $version->update(['scan_status' => 'blocked', 'failure_code' => $exception->reason]);
                    $this->audit->execute($actor, $tenant, 'document.integrity_blocked', 'document_version', $version->id, ['number' => $version->number]);

                    return null;
                }
                $this->audit->execute($actor, $tenant, 'document.download_authorized', 'document_version', $version->id, ['number' => $version->number]);

                return $version;
            });
        } catch (\Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw $exception;
        }
        abort_unless($version, 409, 'Esta versión no está disponible. Su archivo requiere revisión.');

        return response()->streamDownload(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, $version->filename, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Pragma' => 'no-cache']);
    }
}
