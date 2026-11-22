<?php

namespace App\Modules\Documents\Storage;

use App\Modules\Documents\Data\DocumentFileUnavailable;
use App\Modules\Documents\Models\DocumentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PrivateDocumentStore
{
    public function assertPrivateRoot(): string
    {
        $config = config('filesystems.disks.documents');
        if (($config['driver'] ?? null) !== 'local' || ($config['serve'] ?? false)) {
            throw new \RuntimeException('Los documentos requieren disco local privado sin rutas públicas.');
        }
        $root = $config['root'] ?? '';
        if (! str_starts_with($root, DIRECTORY_SEPARATOR) || in_array('..', explode(DIRECTORY_SEPARATOR, $root), true)) {
            throw new \RuntimeException('La ruta privada debe ser absoluta y explícita.');
        }
        $parent = $root;
        $suffix = [];
        while (! file_exists($parent)) {
            array_unshift($suffix, basename($parent));
            $parent = dirname($parent);
        }
        $canonical = rtrim((string) realpath($parent), DIRECTORY_SEPARATOR).($suffix ? DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $suffix) : '');
        if ($canonical === '' || ! is_dir($parent)) {
            throw new \RuntimeException('La raíz privada no es un directorio válido.');
        }
        foreach ([public_path(), ...config('documents.public_roots', [])] as $public) {
            $public = realpath($public) ?: $public;
            if ($canonical === $public || str_starts_with($canonical, rtrim($public, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) || str_starts_with($public, $canonical.DIRECTORY_SEPARATOR)) {
                throw new \RuntimeException('El almacenamiento de documentos debe quedar fuera de las raíces públicas.');
            }
        }

        return $canonical;
    }

    public function path(string $tenant, string $document, string $version): string
    {
        return 'tenants/'.$tenant.'/documents/'.$document.'/versions/'.$version.'/original';
    }

    public function write(string $path, #[\SensitiveParameter] UploadedFile $file): void
    {
        $this->assertPrivateRoot();
        $stream = fopen($file->getRealPath(), 'rb');
        try {
            if (! $stream || ! Storage::disk('documents')->put($path, $stream, ['visibility' => 'private'])) {
                throw new \RuntimeException('No se pudo almacenar el archivo privado.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /** @return resource A bounded, verified copy; callers close it after use. */
    public function verifiedStream(DocumentVersion $version)
    {
        $this->assertPrivateRoot();
        $expected = $this->path($version->tenant_id, $version->document_id, $version->id);
        if (! hash_equals($expected, $version->storage_path)) {
            throw new DocumentFileUnavailable('integrity_mismatch');
        }
        try {
            $source = Storage::disk('documents')->readStream($expected);
        } catch (\Throwable) {
            throw new DocumentFileUnavailable('file_missing');
        }
        if (! is_resource($source)) {
            throw new DocumentFileUnavailable('file_missing');
        }
        $copy = fopen('php://temp/maxmemory:1048576', 'w+b');
        try {
            $size = stream_copy_to_stream($source, $copy, $version->size_bytes + 1);
            rewind($copy);
            $hash = hash_init('sha256');
            hash_update_stream($hash, $copy);
            if ($size !== $version->size_bytes || ! hash_equals($version->sha256, hash_final($hash))) {
                throw new DocumentFileUnavailable('integrity_mismatch');
            }
            rewind($copy);

            return $copy;
        } catch (\Throwable $exception) {
            fclose($copy);
            throw $exception;
        } finally {
            fclose($source);
        }
    }
}
