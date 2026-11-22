<?php

namespace App\Modules\Documents\Actions;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentVersion;
use App\Modules\Documents\Storage\PrivateDocumentStore;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UploadDocumentVersion
{
    public function __construct(private PrivateDocumentStore $store, private AdmitDocumentUpload $admission, private RecordAudit $audit, private QueueDocumentScan $queue) {}

    public function execute(User $actor, Tenant $tenant, ?string $documentId, #[\SensitiveParameter] array $values, #[\SensitiveParameter] UploadedFile $file): DocumentVersion
    {
        Gate::forUser($actor)->authorize('create', [Document::class, $tenant]);
        abort_unless(config('documents.uploads_enabled'), 423, 'Las cargas de documentos todavía no están habilitadas.');
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Document uploads must own their commit boundary.');
        }
        $path = null;
        $id = null;
        $owned = false;
        try {
            $this->store->assertPrivateRoot();

            // One database-scoped infrastructure lock protects managed capacity
            // until commit/rollback, without a cache lease expiring mid-write.
            // Filesystem side effects are never automatically retried.
            return DB::transaction(function () use ($actor, $tenant, $documentId, $values, $file, &$path, &$id, &$owned) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                DB::statement("SET LOCAL lock_timeout = '5s'");
                DB::select('SELECT pg_advisory_xact_lock(?, ?)', [0x415343, 0x444F43]);
                $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
                $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
                Gate::forUser($actor)->authorize('create', [Document::class, $tenant]);
                $document = $documentId ? Document::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($documentId) : null;
                if ($document) {
                    Gate::forUser($actor)->authorize('upload', [$document, $tenant]);
                }
                $rules = ['upload_key' => ['required', 'uuid'], 'expected_version' => $document ? ['required', 'integer', 'min:1'] : ['prohibited']];
                if (! $document) {
                    $rules += ['title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:1000']];
                }
                foreach (['tenant_id', 'uploaded_by', 'storage_path', 'scan_status', 'sha256', 'size_bytes', 'mime_type'] as $field) {
                    $rules[$field] = ['prohibited'];
                }
                $values = Validator::make($values, $rules)->validate();
                if (! $file->isValid()) {
                    throw ValidationException::withMessages(['file' => 'El archivo no se recibió correctamente.']);
                }
                $size = (int) filesize($file->getRealPath());
                if ($size <= 0 || $size > (int) config('documents.max_file_bytes')) {
                    throw ValidationException::withMessages(['file' => 'El archivo está vacío o supera el límite de carga configurado.']);
                }
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
                $filename = mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 180);
                if (! isset(config('documents.types')[$mime]) || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== config('documents.types')[$mime]) {
                    throw ValidationException::withMessages(['file' => 'Usa un PDF o un archivo de texto con tipo y extensión coincidentes.']);
                }
                $hash = hash_file('sha256', $file->getRealPath());
                $payload = hash('sha256', json_encode([$documentId, $values['title'] ?? null, $values['description'] ?? null, $values['expected_version'] ?? null, $filename, $mime, $size, $hash], JSON_THROW_ON_ERROR));
                $key = hash('sha256', $values['upload_key']);
                $existing = DocumentVersion::query()->where('tenant_id', $tenant->id)->where('uploaded_by', $actor->id)->where('upload_key_hash', $key)->first();
                if ($existing) {
                    if (! hash_equals($existing->payload_hash, $payload)) {
                        throw ValidationException::withMessages(['upload_key' => 'Este formulario ya se usó con otro archivo o datos. Abre uno nuevo.']);
                    }

                    return $existing;
                }
                if ($document && (int) $document->version_count !== (int) $values['expected_version']) {
                    throw ValidationException::withMessages(['expected_version' => 'El documento cambió. Recarga y revisa sus versiones antes de subir otra.']);
                }
                $this->admission->execute($tenant->id, $size);
                if (! $document) {
                    $document = Document::create(['tenant_id' => $tenant->id, 'created_by' => $actor->id, 'title' => trim($values['title']), 'description' => $values['description'] ?? null, 'version_count' => 0]);
                }
                $membership = $tenant->memberships()->where('user_id', $actor->id)->sole();
                $id = (string) Str::uuid();
                $path = $this->store->path($tenant->id, $document->id, $id);
                if (Storage::disk('documents')->exists($path)) {
                    throw new \RuntimeException('La ruta de la versión ya existe.');
                }
                $version = new DocumentVersion;
                $version->id = $id;
                $version->fill([
                    'tenant_id' => $tenant->id, 'document_id' => $document->id, 'number' => $document->version_count + 1,
                    'uploaded_by' => $actor->id, 'upload_key_hash' => $key, 'payload_hash' => $payload,
                    'filename' => $filename, 'mime_type' => $mime, 'size_bytes' => $size, 'sha256' => $hash, 'storage_path' => $path,
                    'scan_status' => 'quarantined', 'scan_generation' => 1, 'scan_requested_by' => $actor->id,
                    'source_membership_id' => $membership->id, 'source_access_version' => $membership->access_version,
                ])->save();
                $owned = true;
                $this->store->write($path, $file);
                $document->increment('version_count');
                $this->audit->execute($actor, $tenant, 'document.version_uploaded', 'document_version', $version->id, ['document_id' => $document->id, 'number' => $version->number]);
                $this->queue->execute($version);

                return $version;
            });
        } catch (\Throwable $exception) {
            if ($owned && $path && $id) {
                try {
                    if (! DocumentVersion::query()->where('tenant_id', $tenant->id)->whereKey($id)->exists()) {
                        Storage::disk('documents')->delete($path);
                    }
                } catch (\Throwable) {
                    Log::warning('Document upload cleanup pending.', ['tenant_id' => $tenant->id, 'version_id' => $id]);
                }
            }
            if ($exception instanceof ValidationException || $exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException || $exception instanceof HttpException) {
                throw $exception;
            }
            throw new \RuntimeException('No se pudo almacenar la versión. Revisa el estado y vuelve a intentarlo con el mismo formulario.');
        }
    }
}
