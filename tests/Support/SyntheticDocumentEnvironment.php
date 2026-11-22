<?php

namespace Tests\Support;

use App\Modules\Documents\Data\CapacitySnapshot;
use App\Modules\Documents\Storage\CapacityProbe;
use App\Modules\Documents\Storage\MalwareScanner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

final class SyntheticDocumentEnvironment
{
    public static function configure(bool $clearDisk = true): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'pgsql'
            || config('database.connections.pgsql.database') !== 'ascanio_test' || config('database.connections.pgsql.url')) {
            throw new \RuntimeException('Synthetic documents require the dedicated test environment.');
        }
        config([
            'documents.uploads_enabled' => true, 'documents.max_file_bytes' => 1048576,
            'documents.tenant_limit_bytes' => 8388608, 'documents.app_limit_bytes' => 16777216,
            'documents.account_reserve_bytes' => 1048576, 'documents.account_reserve_inodes' => 10,
            'filesystems.disks.documents.root' => storage_path('framework/testing/disks/documents'),
        ]);
        if ($clearDisk) {
            Storage::fake('documents');
        }
        app()->instance(CapacityProbe::class, new class implements CapacityProbe
        {
            public function snapshot(): CapacitySnapshot
            {
                return new CapacitySnapshot(104857600, 10000, CarbonImmutable::now());
            }
        });
        app()->instance(MalwareScanner::class, new class implements MalwareScanner
        {
            public function available(): bool
            {
                return true;
            }

            public function scan($stream): string
            {
                // Synthetic marker only. This is not a production malware scanner.
                return str_contains(stream_get_contents($stream), 'synthetic-infected') ? 'infected' : 'clean';
            }
        });
    }
}
