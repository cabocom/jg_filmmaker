<?php

declare(strict_types=1);

namespace App\Services\Media;

use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaStorageService
{
    /**
     * Resuelve el disco preferente de almacenamiento multimedia.
     */
    public function disk(string $name = 'media'): Filesystem
    {
        $configured = config('filesystems.default_media_disk', 'local');

        if (config()->has("filesystems.disks.{$name}")) {
            return Storage::disk($name);
        }

        return Storage::disk($configured);
    }

    /**
     * Comprueba si el archivo existe en el disco de medios o en los discos de compatibilidad local/público.
     */
    public function exists(string $path): bool
    {
        if ($this->disk()->exists($path)) {
            return true;
        }

        if (Storage::disk('local')->exists($path)) {
            return true;
        }

        return Storage::disk('public')->exists($path);
    }

    /**
     * Devuelve el contenido en crudo de un archivo.
     */
    public function get(string $path): ?string
    {
        if ($this->disk()->exists($path)) {
            return $this->disk()->get($path);
        }

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->get($path);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->get($path);
        }

        return null;
    }

    /**
     * Almacena contenido en el disco de medios.
     */
    public function put(string $path, mixed $contents): bool
    {
        return $this->disk()->put($path, $contents);
    }

    /**
     * Elimina uno o varios archivos del almacenamiento multimedia.
     *
     * @param  string|array<int, string>  $paths
     */
    public function delete(string|array $paths): bool
    {
        $pathList = is_array($paths) ? $paths : [$paths];
        $allSuccess = true;

        foreach ($pathList as $p) {
            $deleted = false;
            if ($this->disk()->exists($p)) {
                $deleted = $this->disk()->delete($p) || $deleted;
            }
            if (Storage::disk('local')->exists($p)) {
                $deleted = Storage::disk('local')->delete($p) || $deleted;
            }
            if (Storage::disk('public')->exists($p)) {
                $deleted = Storage::disk('public')->delete($p) || $deleted;
            }
            $allSuccess = $allSuccess && $deleted;
        }

        return $allSuccess;
    }

    /**
     * Genera una URL pre-firmada temporal (Presigned URL) para S3 / Cloudflare R2 / MinIO si está disponible.
     */
    public function temporaryUrl(string $path, DateTimeInterface|int $expiration): ?string
    {
        $activeDisk = $this->disk();

        if (method_exists($activeDisk, 'temporaryUrl') && config('filesystems.disks.media.driver') === 's3') {
            try {
                $exp = is_int($expiration) ? now()->addSeconds($expiration) : $expiration;

                return $activeDisk->temporaryUrl($path, $exp);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Sirve el archivo multimedia mediante redirección Presigned URL (S3/R2), X-Accel-Redirect (Nginx) o Streaming nativo.
     *
     * @param  array<string, string>  $headers
     */
    public function serve(?string $path, string $notFoundMessage = 'Archivo no encontrado.', array $headers = []): Response|StreamedResponse|RedirectResponse
    {
        if (empty($path)) {
            abort(404, $notFoundMessage);
        }

        $allHeaders = array_merge(['Cache-Control' => 'private, max-age=3600'], $headers);

        // 1. Si el disco de medios es remoto S3 / Cloudflare R2 / MinIO, emitir Presigned GET URL
        $presignedUrl = $this->temporaryUrl($path, now()->addMinutes(15));
        if ($presignedUrl !== null) {
            return redirect()->away($presignedUrl);
        }

        // 2. Si está habilitada la aceleración por kernel con Nginx (X-Accel-Redirect)
        if (config('filesystems.use_x_accel', false) && Storage::disk('local')->exists($path)) {
            return response('', 200, array_merge($allHeaders, [
                'X-Accel-Redirect' => '/protected-media/'.ltrim($path, '/'),
                'Content-Type' => mime_content_type(Storage::disk('local')->path($path)) ?: 'application/octet-stream',
            ]));
        }

        // 3. Fallback: Streaming seguro nativo mediante Flysystem
        if ($this->disk()->exists($path)) {
            return $this->disk()->response($path, null, $allHeaders);
        }

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->response($path, null, $allHeaders);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->response($path, null, $allHeaders);
        }

        abort(404, $notFoundMessage);
    }
}
