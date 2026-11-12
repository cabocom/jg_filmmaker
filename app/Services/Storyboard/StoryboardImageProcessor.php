<?php

declare(strict_types=1);

namespace App\Services\Storyboard;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoryboardImageProcessor
{
    /**
     * @return array{mime_type: string, data: string}|null
     */
    public function getStorageImageData(?string $path): ?array
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $bytes = Storage::disk('public')->get($path);
        if (empty($bytes)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';

        return [
            'mime_type' => $mime,
            'data' => base64_encode($bytes),
        ];
    }

    /**
     * @return array{mime_type: string, data: string}|null
     */
    public function getUploadedOrImageData(mixed $file): ?array
    {
        if ($file instanceof UploadedFile) {
            $path = $file->getRealPath();
            if ($path && file_exists($path)) {
                $bytes = file_get_contents($path);
                if (! empty($bytes)) {
                    return [
                        'mime_type' => $file->getMimeType() ?: 'image/jpeg',
                        'data' => base64_encode($bytes),
                    ];
                }
            }
        }

        if (is_string($file)) {
            if (Storage::disk('public')->exists($file)) {
                return $this->getStorageImageData($file);
            }
            if (file_exists($file)) {
                $bytes = file_get_contents($file);
                if (! empty($bytes)) {
                    $mime = mime_content_type($file) ?: 'image/jpeg';

                    return [
                        'mime_type' => $mime,
                        'data' => base64_encode($bytes),
                    ];
                }
            }
        }

        return null;
    }

    public function getDummyJpegBytes(): string
    {
        return (string) base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
    }
}
