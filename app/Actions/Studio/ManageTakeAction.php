<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Shot;
use App\Models\Take;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

final readonly class ManageTakeAction
{
    /**
     * Sube y analiza técnicamente el archivo de video para una toma.
     *
     * @return array{file_path: string, technical_meta: array<string, mixed>}
     */
    public function uploadVideo(Take $take, UploadedFile $videoUpload): array
    {
        $allowedMimes = [
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/webm' => 'webm',
            'video/x-m4v' => 'm4v',
        ];

        $mime = (string) $videoUpload->getMimeType();
        $extension = $allowedMimes[$mime] ?? 'mp4';

        if ($take->file_path) {
            if (Storage::disk('local')->exists($take->file_path)) {
                Storage::disk('local')->delete($take->file_path);
            }
            if (Storage::disk('public')->exists($take->file_path)) {
                Storage::disk('public')->delete($take->file_path);
            }
        }

        $storagePath = $take->generateStoragePath($extension);
        $videoUpload->storeAs('', $storagePath, 'local');

        $absolutePath = Storage::disk('local')->path($storagePath);
        $probedMeta = $this->probeVideoFile($absolutePath);

        $take->update([
            'file_path' => $storagePath,
            'technical_meta' => array_merge($take->technical_meta ?? [], $probedMeta),
        ]);

        return [
            'file_path' => $storagePath,
            'technical_meta' => $take->technical_meta,
        ];
    }

    /**
     * Sonda metadatos técnicos del archivo de vídeo mediante ffprobe.
     *
     * @return array<string, mixed>
     */
    public function probeVideoFile(string $absolutePath): array
    {
        $defaultMeta = ['probed' => false, 'duration_seconds' => null, 'resolution' => null, 'fps' => null, 'codec' => null];
        if (! file_exists($absolutePath)) {
            return $defaultMeta;
        }

        try {
            $process = Process::timeout(15)->run(['ffprobe', '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $absolutePath]);
            if (! $process->successful()) {
                return $defaultMeta;
            }

            $data = json_decode($process->output(), true);
            if (! is_array($data) || empty($data['streams'])) {
                return $defaultMeta;
            }

            $videoStream = collect($data['streams'])->firstWhere('codec_type', 'video');
            if (! $videoStream) {
                return $defaultMeta;
            }

            return $this->formatStreamMetadata($videoStream, $data['format'] ?? []);
        } catch (\Throwable) {
            return $defaultMeta;
        }
    }

    private function formatStreamMetadata(array $stream, array $format): array
    {
        $duration = (float) ($stream['duration'] ?? $format['duration'] ?? 0);
        $w = (int) ($stream['width'] ?? 0);
        $h = (int) ($stream['height'] ?? 0);
        $codec = (string) ($stream['codec_name'] ?? '');

        return [
            'probed' => true,
            'duration_seconds' => $duration > 0 ? round($duration, 2) : null,
            'resolution' => ($w > 0 && $h > 0) ? "{$w}x{$h}" : null,
            'fps' => $this->parseFps((string) ($stream['r_frame_rate'] ?? $stream['avg_frame_rate'] ?? '')),
            'codec' => ! empty($codec) ? strtolower($codec) : null,
            'pix_fmt' => (string) ($stream['pix_fmt'] ?? ''),
            'bit_rate' => (int) ($stream['bit_rate'] ?? $format['bit_rate'] ?? 0),
        ];
    }

    private function parseFps(string $fpsRaw): ?float
    {
        if (! empty($fpsRaw) && str_contains($fpsRaw, '/')) {
            [$num, $den] = explode('/', $fpsRaw, 2);
            if ((float) $den > 0) {
                return round((float) $num / (float) $den, 2);
            }
        }

        return null;
    }

    /**
     * Añade una nueva toma al plano bloqueando por concurrencia.
     */
    public function createTake(Shot $shot, bool $isGood, ?string $editorialNotes): Take
    {
        return DB::transaction(function () use ($shot, $isGood, $editorialNotes) {
            Shot::whereKey($shot->id)->lockForUpdate()->first();
            $maxTakeNumber = $shot->takes()->max('take_number') ?? 0;

            return $shot->takes()->create([
                'take_number' => $maxTakeNumber + 1,
                'is_good_take' => $isGood,
                'editorial_notes' => $editorialNotes ?: null,
                'technical_meta' => [
                    'resolution' => '3840x2160',
                    'codec' => 'prores422hq',
                    'fps' => 24.0,
                ],
            ]);
        });
    }

    /**
     * Alterna la marca de toma buena.
     */
    public function toggleGoodTake(Take $take): void
    {
        $take->update(['is_good_take' => ! $take->is_good_take]);
    }

    /**
     * Guarda las notas editoriales de la toma.
     */
    public function saveNotes(Take $take, ?string $notes): void
    {
        $take->update(['editorial_notes' => $notes ?: null]);
    }

    /**
     * Elimina físicamente la toma y sus archivos asociados si existen.
     */
    public function deleteTake(Take $take): void
    {
        if ($take->file_path) {
            if (Storage::disk('local')->exists($take->file_path)) {
                Storage::disk('local')->delete($take->file_path);
            }
            if (Storage::disk('public')->exists($take->file_path)) {
                Storage::disk('public')->delete($take->file_path);
            }
        }

        $take->delete();
    }
}
