<?php

declare(strict_types=1);

namespace App\Actions\Broadcast;

use App\Jobs\NormalizeBroadcastMediaJob;
use App\Models\TvMediaNormalization;
use App\Services\Broadcast\BroadcastProfileValidator;
use App\Services\Broadcast\TvMediaPathResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

final class NormalizeBroadcastMediaAction
{
    public function __construct(
        private readonly TvMediaPathResolver $pathResolver,
        private readonly BroadcastProfileValidator $validator,
    ) {}

    public function enqueue(string $relativePath, string $profile = '720p30'): TvMediaNormalization
    {
        $this->validator->assertValidProfile($profile);
        $cleanPath = $this->pathResolver->normalizeRelativePath($relativePath, requireMp4Extension: true);
        $absPath = $this->pathResolver->resolveAbsolutePath($cleanPath, requireMp4Extension: true);

        if (! is_file($absPath)) {
            throw new InvalidArgumentException("El archivo no existe: {$cleanPath}");
        }

        return DB::transaction(function () use ($cleanPath, $absPath, $profile): TvMediaNormalization {
            $existing = TvMediaNormalization::query()
                ->where('relative_path', $cleanPath)
                ->whereIn('stage', ['queued', 'normalizing'])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new RuntimeException('El archivo ya está en proceso de normalización.', 409);
            }

            $norm = TvMediaNormalization::create([
                'relative_path' => $cleanPath,
                'target_profile' => $profile,
                'stage' => 'queued',
                'probe_before' => $this->validator->inspectMp4($absPath, $profile),
            ]);

            NormalizeBroadcastMediaJob::dispatch($norm->id)->onQueue('broadcast');

            return $norm;
        });
    }

    public function process(TvMediaNormalization $norm): TvMediaNormalization
    {
        $norm->update(['stage' => 'normalizing']);
        $absPath = $this->pathResolver->resolveAbsolutePath($norm->relative_path, requireMp4Extension: true);
        $tmpPath = "{$absPath}.normalizing.{$norm->id}.mp4";

        try {
            $this->transcode($absPath, $tmpPath, $norm->target_profile);
            $afterProbe = $this->validator->inspectMp4($tmpPath, $norm->target_profile);

            if (! ($afterProbe['broadcast_compliant'] ?? false)) {
                $reason = implode('; ', $afterProbe['reasons'] ?? ['Validación post-normalización fallida']);
                throw new RuntimeException("El archivo normalizado no cumple el perfil {$norm->target_profile}: {$reason}");
            }

            File::move($tmpPath, $absPath);
            Cache::put("tv_media_probe:{$norm->target_profile}:{$norm->relative_path}", $afterProbe, 86400);

            $norm->update([
                'stage' => 'ready',
                'probe_after' => $afterProbe,
                'error_message' => null,
            ]);

            return $norm;
        } finally {
            if (File::exists($tmpPath)) {
                File::delete($tmpPath);
            }
        }
    }

    private function transcode(string $absPath, string $tmpPath, string $profile): void
    {
        if ($this->shouldUseMock()) {
            File::ensureDirectoryExists(dirname($tmpPath));
            File::put($tmpPath, "MOCK_NORMALIZED_MP4_{$profile}");

            return;
        }

        $dimensions = $this->validator->dimensionsFor($profile);
        $w = $dimensions['width'];
        $h = $dimensions['height'];
        $vf = "scale={$w}:{$h}:force_original_aspect_ratio=decrease,pad={$w}:{$h}:(ow-iw)/2:(oh-ih)/2";

        $result = Process::timeout(600)->run([
            'ffmpeg', '-y', '-i', $absPath,
            '-vf', $vf,
            '-c:v', 'libx264', '-preset', 'veryfast', '-r', '30', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-ar', '48000', '-ac', '2',
            '-movflags', '+faststart',
            $tmpPath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("Error en FFmpeg durante la normalización: {$result->errorOutput()}");
        }
    }

    private function shouldUseMock(): bool
    {
        return (function_exists('app') && app()->environment('testing'))
            || (bool) config('services.filmmaker.mock_ffmpeg', false);
    }
}
