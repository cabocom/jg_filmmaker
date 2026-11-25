<?php

declare(strict_types=1);

namespace App\Services\Broadcast\Visuals;

use App\Contracts\Broadcast\ShotVisualStrategyInterface;
use App\Models\BroadcastJob;
use App\Models\Shot;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

final class StaticCardShotVisual implements ShotVisualStrategyInterface
{
    public function visualType(): string
    {
        return 'image';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function generateVisual(BroadcastJob $job, Shot $shot, array $context = []): string
    {
        $shotNumber = $shot->shot_number ?? 1;
        $outputDir = storage_path("app/private/broadcast/{$job->id}/visuals");
        File::ensureDirectoryExists($outputDir);

        $outputPath = "{$outputDir}/shot_{$shotNumber}.png";

        if (function_exists('imagecreatetruecolor')) {
            $this->renderGdImage($job, $context, $outputPath);
        } elseif (! $this->renderFfmpegImage($job, $outputPath)) {
            $this->renderFallbackPng($outputPath);
        }

        return $outputPath;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function renderGdImage(BroadcastJob $job, array $context, string $outputPath): void
    {
        $is720p = (($job->artifacts['resolution_profile'] ?? '1080p30') === '720p30');
        $width = $is720p ? 1280 : 1920;
        $height = $is720p ? 720 : 1080;
        $image = imagecreatetruecolor($width, $height);

        $bgColor = imagecolorallocate($image, 15, 23, 42) ?: 0;
        $textColor = imagecolorallocate($image, 248, 250, 252) ?: 0;
        $subColor = imagecolorallocate($image, 148, 163, 184) ?: 0;
        $accentColor = imagecolorallocate($image, 59, 130, 246) ?: 0;
        $barBgColor = imagecolorallocate($image, 51, 65, 85) ?: 0;

        imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

        $title = (string) ($job->title ?: 'Broadcast');
        $speaker = (string) ($context['speaker'] ?? 'Presentador');
        $sources = is_array($job->sources) ? $job->sources : [];
        $source = (string) ($sources[0] ?? $job->channel_id ?? 'Canal');
        $progress = max(0.0, min(1.0, (float) ($context['progress'] ?? 0.5)));

        imagestring($image, 5, 80, 80, "[ {$speaker} ]", $accentColor);
        imagestring($image, 5, 80, 140, substr($title, 0, 100), $textColor);
        imagestring($image, 4, 80, 200, "Fuente: {$source}", $subColor);

        $barY = $height - 60;
        imagefilledrectangle($image, 80, $barY, $width - 80, $barY + 12, $barBgColor);
        $fillWidth = (int) (($width - 160) * $progress);
        if ($fillWidth > 0) {
            imagefilledrectangle($image, 80, $barY, 80 + $fillWidth, $barY + 12, $accentColor);
        }

        imagepng($image, $outputPath);
        imagedestroy($image);
    }

    private function renderFfmpegImage(BroadcastJob $job, string $outputPath): bool
    {
        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
        $is720p = (($artifacts['resolution_profile'] ?? '1080p30') === '720p30');
        $size = $is720p ? '1280x720' : '1920x1080';

        $result = Process::timeout(15)->run([
            'ffmpeg', '-y', '-f', 'lavfi', '-i', "color=c=0x0f172a:s={$size}:d=1", '-frames:v', '1', $outputPath,
        ]);

        return $result->successful() && file_exists($outputPath) && filesize($outputPath) > 0;
    }

    private function renderFallbackPng(string $outputPath): void
    {
        if (function_exists('gzcompress')) {
            $compressed = gzcompress("\x00\x0f\x17\x2a");
            if ($compressed !== false) {
                $ihdr = 'IHDR'.pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0);
                $idat = 'IDAT'.$compressed;
                $iend = 'IEND';

                $png = "\x89PNG\r\n\x1a\n"
                    .pack('N', 13).$ihdr.pack('N', crc32($ihdr))
                    .pack('N', strlen($compressed)).$idat.pack('N', crc32($idat))
                    .pack('N', 0).$iend.pack('N', crc32($iend));

                file_put_contents($outputPath, $png);

                return;
            }
        }

        $png1x1 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNgYmBgAAAABAABJzQnCgAAAABJRU5ErkJggg==');
        file_put_contents($outputPath, $png1x1);
    }
}
