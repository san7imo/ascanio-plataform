<?php

namespace App\Modules\Documents\Jobs;

use App\Models\User;
use App\Modules\Documents\Data\DocumentFileUnavailable;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentVersion;
use App\Modules\Documents\Storage\MalwareScanner;
use App\Modules\Documents\Storage\PrivateDocumentStore;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ScanDocumentVersion implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public array $backoff = [15, 60, 180];

    public function __construct(public string $tenantId, public string $versionId, public string $requesterId, public int $generation) {}

    public function displayName(): string
    {
        return 'tenant:'.$this->tenantId.':document-scan:'.$this->versionId;
    }

    public function handle(TenantContext $context, PrivateDocumentStore $store, MalwareScanner $scanner, RecordAudit $audit): void
    {
        $context->clear();
        try {
            $retry = DB::transaction(function () use ($context, $store, $scanner, $audit) {
                $actor = User::query()->lockForUpdate()->findOrFail($this->requesterId);
                $tenant = Tenant::query()->lockForUpdate()->findOrFail($this->tenantId);
                $version = DocumentVersion::query()->where('tenant_id', $tenant->id)->lockForUpdate()->find($this->versionId);
                if (! $version || $version->scan_generation !== $this->generation || $version->scan_requested_by !== $actor->id
                    || ! in_array($version->scan_status, ['quarantined', 'scan_failed'], true)) {
                    return false;
                }
                $document = Document::query()->where('tenant_id', $tenant->id)->findOrFail($version->document_id);
                try {
                    $member = $tenant->memberships()->whereKey($version->source_membership_id)->where('user_id', $actor->id)->first();
                    if (! $member || (int) $member->access_version !== (int) $version->source_access_version) {
                        throw new AuthorizationException;
                    }
                    $context->establish($tenant, $member);
                    Gate::forUser($actor)->authorize('upload', [$document, $tenant]);
                } catch (AuthorizationException) {
                    $version->update(['scan_status' => 'blocked', 'failure_code' => 'authority_revoked']);

                    return false;
                }
                try {
                    $stream = $store->verifiedStream($version);
                } catch (DocumentFileUnavailable $exception) {
                    $version->update(['scan_status' => 'blocked', 'failure_code' => $exception->reason]);
                    $audit->execute($actor, $tenant, 'document.integrity_blocked', 'document_version', $version->id, ['number' => $version->number]);

                    return false;
                }
                try {
                    $result = $scanner->available() ? $scanner->scan($stream) : 'unavailable';
                } catch (\Throwable) {
                    $result = 'error';
                } finally {
                    fclose($stream);
                }
                if (! in_array($result, ['clean', 'infected'], true)) {
                    $version->update(['scan_status' => 'scan_failed', 'failure_code' => $result === 'unavailable' ? 'scanner_unavailable' : 'scan_error']);
                    $audit->execute($actor, $tenant, 'document.scan_failed', 'document_version', $version->id, ['generation' => $this->generation]);

                    return true;
                }
                if ($result === 'clean') {
                    try {
                        // Recheck the original after scanning the frozen copy.
                        fclose($store->verifiedStream($version));
                    } catch (DocumentFileUnavailable $exception) {
                        $version->update(['scan_status' => 'blocked', 'failure_code' => $exception->reason]);
                        $audit->execute($actor, $tenant, 'document.integrity_blocked', 'document_version', $version->id, ['number' => $version->number]);

                        return false;
                    }
                }
                $version->update(['scan_status' => $result, 'failure_code' => $result === 'infected' ? 'malware_detected' : null, 'scanned_at' => now()]);
                $audit->execute($actor, $tenant, 'document.scan_'.$result, 'document_version', $version->id, ['generation' => $this->generation, 'number' => $version->number]);

                return false;
            }, 3);
            if ($retry) {
                throw new \RuntimeException('El análisis del documento falló. Revisa su estado y el escáner antes de reintentar.');
            }
        } finally {
            $context->clear();
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $context = app(TenantContext::class);
        $context->clear();
        try {
            DocumentVersion::query()->where('tenant_id', $this->tenantId)->whereKey($this->versionId)
                ->where('scan_generation', $this->generation)->where('scan_requested_by', $this->requesterId)->whereIn('scan_status', ['quarantined', 'scan_failed'])
                ->update(['scan_status' => 'scan_failed', 'failure_code' => 'attempts_exhausted']);
        } finally {
            $context->clear();
        }
    }
}
