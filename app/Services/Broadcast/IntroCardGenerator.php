<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Models\BroadcastJob;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

final class IntroCardGenerator
{
    private const int INTRO_DURATION_SECONDS = 3;

    /**
     * Genera una pantalla de inicio de 3 segundos (30fps CFR, audio silencio AAC 48kHz stereo).
     *
     * @param  array<string, mixed>  $artifacts
     */
    public function generate(BroadcastJob $job, array $artifacts, string $tempDir): string
    {
        File::ensureDirectoryExists($tempDir);

        $is720p = (($artifacts['resolution_profile'] ?? '1080p30') === '720p30');
        $width = $is720p ? 1280 : 1920;
        $height = $is720p ? 720 : 1080;

        $pngPath = "{$tempDir}/intro_card.png";
        $videoPath = "{$tempDir}/intro_card.mp4";

        $this->renderCardPng($job, $artifacts, $pngPath, $width, $height);

        $preset = $is720p ? ['-preset', 'veryfast'] : [];
        $cmd = [
            'ffmpeg', '-y',
            '-loop', '1', '-t', (string) self::INTRO_DURATION_SECONDS, '-i', $pngPath,
            '-f', 'lavfi', '-t', (string) self::INTRO_DURATION_SECONDS, '-i', 'anullsrc=channel_layout=stereo:sample_rate=48000',
            '-r', '30', '-fps_mode', 'cfr', '-video_track_timescale', '30000',
            '-c:v', 'libx264', '-profile:v', 'high', '-level', '4.1', '-pix_fmt', 'yuv420p', ...$preset,
            '-c:a', 'aac', '-ar', '48000', '-ac', '2',
            '-shortest', '-movflags', '+faststart', $videoPath,
        ];

        $result = Process::timeout(60)->run($cmd);
        if (! $result->successful()) {
            throw new RuntimeException("Fallo al generar intro_card.mp4 para el job {$job->id}: ".$result->errorOutput());
        }

        return $videoPath;
    }

    /**
     * @param  array<string, mixed>  $artifacts
     */
    private function renderCardPng(BroadcastJob $job, array $artifacts, string $pngPath, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new RuntimeException('No se pudo inicializar el lienzo GD para la intro card.');
        }

        $bg = imagecolorallocate($image, 11, 17, 32);
        $accent = imagecolorallocate($image, 56, 189, 248);
        $white = imagecolorallocate($image, 248, 250, 252);
        $muted = imagecolorallocate($image, 148, 163, 184);

        imagefill($image, 0, 0, $bg);
        $marginX = (int) round($width * 0.08);
        imagefilledrectangle($image, $marginX, (int) round($height * 0.28), $marginX + (int) round($width * 0.06), (int) round($height * 0.28) + 6, $accent);

        $series = BroadcastSeriesHelper::seriesNameForFormat($job->format);
        $title = trim((string) ($job->title ?? ''));
        $sources = is_array($job->sources) ? $job->sources : [];
        $sourceUrl = trim((string) ($sources[0] ?? ''));
        $visualCredit = trim((string) ($artifacts['visual_credit'] ?? ''));
        $footer = implode(' · ', array_filter([$sourceUrl, $visualCredit], static fn (string $v): bool => $v !== ''));

        $font = $this->resolveFontPath();
        $scale = $width / 1920;

        if ($font !== null) {
            imagettftext($image, (int) round(30 * $scale), 0, $marginX, (int) round($height * 0.25), $accent, $font, mb_strtoupper($series));
            $y = (int) round($height * 0.40);
            foreach ($this->wrapText($title, 38) as $line) {
                imagettftext($image, (int) round(54 * $scale), 0, $marginX, $y, $white, $font, $line);
                $y += (int) round(76 * $scale);
            }
            if ($footer !== '') {
                imagettftext($image, (int) round(22 * $scale), 0, $marginX, (int) round($height * 0.86), $muted, $font, $footer);
            }
        } else {
            imagestring($image, 5, $marginX, (int) round($height * 0.23), $series, $accent);
            imagestring($image, 5, $marginX, (int) round($height * 0.42), $title, $white);
            if ($footer !== '') {
                imagestring($image, 4, $marginX, (int) round($height * 0.85), $footer, $muted);
            }
        }

        imagepng($image, $pngPath);
        imagedestroy($image);
    }

    /**
     * @return list<string>
     */
    private function wrapText(string $text, int $maxChars): array
    {
        if ($text === '') {
            return [];
        }

        $wrapped = wordwrap($text, $maxChars, "\n", true);

        return array_slice(explode("\n", $wrapped), 0, 3);
    }

    private function resolveFontPath(): ?string
    {
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
