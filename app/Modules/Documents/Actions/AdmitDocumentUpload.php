<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Documents\Storage\CapacityProbe;
use App\Modules\Documents\Storage\MalwareScanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmitDocumentUpload
{
    public function __construct(private CapacityProbe $capacity, private MalwareScanner $scanner) {}

    public function execute(string $tenantId, int $size): void
    {
        if (! config('documents.uploads_enabled')) {
            abort(423, 'Las cargas de documentos todavía no están habilitadas.');
        }
        $fileMax = (int) config('documents.max_file_bytes');
        $tenantMax = (int) config('documents.tenant_limit_bytes');
        $appMax = (int) config('documents.app_limit_bytes');
        $reserve = (int) config('documents.account_reserve_bytes');
        $inodes = (int) config('documents.account_reserve_inodes');
        if (min($fileMax, $tenantMax, $appMax, $reserve, $inodes) <= 0 || ! $this->scanner->available()) {
            throw ValidationException::withMessages(['file' => 'El almacenamiento y el análisis de seguridad todavía no están disponibles.']);
        }
        if ($size <= 0 || $size > $fileMax) {
            throw ValidationException::withMessages(['file' => 'El archivo está vacío o supera el límite de carga configurado.']);
        }
        try {
            $snapshot = $this->capacity->snapshot();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'No se pudo comprobar la capacidad disponible. Inténtalo más tarde.']);
        }
        if ($snapshot->measuredAt->isFuture() || $snapshot->measuredAt->lt(now()->subSeconds((int) config('documents.capacity_max_age_seconds')))
            || $snapshot->availableBytes - $reserve < $size || $snapshot->availableInodes - $inodes < 8) {
            throw ValidationException::withMessages(['file' => 'La comprobación de capacidad no permite recibir este archivo ahora.']);
        }
        $tenantBytes = (int) DB::table('document_versions')->where('tenant_id', $tenantId)->sum('size_bytes');
        // Deliberately global infrastructure aggregate for one shared volume.
        // Called only under its admission lock, never exposed in product queries.
        $managedBytes = (int) DB::table('document_versions')->sum('size_bytes');
        if ($size > $tenantMax - $tenantBytes || $size > $appMax - $managedBytes) {
            throw ValidationException::withMessages(['file' => 'La capacidad reservada para documentos no permite esta carga.']);
        }
    }
}
