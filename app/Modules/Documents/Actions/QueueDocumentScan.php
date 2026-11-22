<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Documents\Jobs\ScanDocumentVersion;
use App\Modules\Documents\Models\DocumentVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueueDocumentScan
{
    public function execute(DocumentVersion $version): void
    {
        $tenant = $version->tenant_id;
        $id = $version->id;
        $requester = $version->scan_requested_by;
        $generation = $version->scan_generation;
        DB::afterCommit(function () use ($tenant, $id, $requester, $generation) {
            try {
                ScanDocumentVersion::dispatch($tenant, $id, $requester, $generation);
            } catch (\Throwable $exception) {
                DocumentVersion::query()->where('tenant_id', $tenant)->whereKey($id)->where('scan_generation', $generation)
                    ->where('scan_status', 'quarantined')->update(['scan_status' => 'scan_failed', 'failure_code' => 'queue_unavailable']);
                Log::warning('Document scan dispatch failed.', ['tenant_id' => $tenant, 'version_id' => $id, 'exception_type' => $exception::class]);
            }
        });
    }
}
