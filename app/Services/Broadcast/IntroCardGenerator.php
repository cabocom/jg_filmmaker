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
     * Genera una pantalla de inicio de 3 segundos (30fps CFR, audio silencio AAC 48kHz stereo)
     * mediante FFmpeg + libass UTF-8 (sin dependencia de extensión PHP GD).
     *
     * @param  array<string, mixed>  $artifacts
     */
    public function generate(BroadcastJob $job, array $artifacts, string $tempDir): string
    {
        File::ensureDirectoryExists($tempDir);

        $is720p = (($artifacts['resolution_profile'] ?? '1080p30') === '720p30');
        $width = $is720p ? 1280 : 1920;
        $height = $is720p ? 720 : 1080;

        $assPath = "{$tempDir}/intro_card.ass";
        $videoPath = "{$tempDir}/intro_card.mp4";

        File::put($assPath, $this->buildIntroAssContent($job, $artifacts, $width, $height));

        $escapedAss = str_replace(['\\', ':', "'"], ['/', '\\:', "\\'"], $assPath);
        $preset = $is720p ? ['-preset', 'veryfast'] : [];

        $cmd = [
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', "color=c=0x0b1120:s={$width}x{$height}:d=".self::INTRO_DURATION_SECONDS.':r=30',
            '-f', 'lavfi', '-t', (string) self::INTRO_DURATION_SECONDS, '-i', 'anullsrc=channel_layout=stereo:sample_rate=48000',
            '-vf', "subtitles={$escapedAss}",
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
     * Construye un guion ASS UTF-8 con soporte nativo para tildes y caracteres latinos.
     *
     * @param  array<string, mixed>  $artifacts
     */
    public function buildIntroAssContent(BroadcastJob $job, array $artifacts, int $width = 1920, int $height = 1080): string
    {
        $series = mb_strtoupper(BroadcastSeriesHelper::seriesNameForFormat($job->format), 'UTF-8');
        $title = trim((string) ($job->title ?? ''));
        $sources = is_array($job->sources) ? $job->sources : [];
        $sourceUrl = trim((string) ($sources[0] ?? ''));
        $visualCredit = trim((string) ($artifacts['visual_credit'] ?? ''));
        $footer = implode(' · ', array_filter([$sourceUrl, $visualCredit], static fn (string $v): bool => $v !== ''));

        $scale = $width / 1920;
        $seriesSize = (int) round(34 * $scale);
        $titleSize = (int) round(64 * $scale);
        $footerSize = (int) round(26 * $scale);
        $marginX = (int) round(140 * $scale);

        $end = sprintf('0:00:%02d.00', self::INTRO_DURATION_SECONDS);
        $lines = [
            '[Script Info]',
            'Title: Broadcast Intro Card',
            'ScriptType: v4.00+',
            'WrapStyle: 0',
            'ScaledBorderAndShadow: yes',
            'YCbCr Matrix: TV.709',
            "PlayResX: {$width}",
            "PlayResY: {$height}",
            '',
            '[V4+ Styles]',
            'Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding',
            "Style: Series,Arial,{$seriesSize},&H00F8BD38,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,2,0,1,0,0,7,{$marginX},{$marginX},".(int) round($height * 0.22).',1',
            "Style: Title,Arial,{$titleSize},&H00F8FAFC,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,0,0,1,0,0,4,{$marginX},{$marginX},0,1",
            "Style: Footer,Arial,{$footerSize},&H00B8A394,&H000000FF,&H00000000,&H80000000,0,0,0,0,100,100,0,0,1,0,0,1,{$marginX},{$marginX},".(int) round($height * 0.10).',1',
            '',
            '[Events]',
            'Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text',
            'Dialogue: 0,0:00:00.00,'.$end.',Series,,0,0,0,,{\fad(250,250)}'.$this->escapeAssText($series),
            'Dialogue: 0,0:00:00.00,'.$end.',Title,,0,0,0,,{\fad(300,250)}'.$this->escapeAssText($title),
        ];

        if ($footer !== '') {
            $lines[] = 'Dialogue: 0,0:00:00.00,'.$end.',Footer,,0,0,0,,{\fad(350,250)}'.$this->escapeAssText($footer);
        }

        return implode("\n", $lines)."\n";
    }

    private function escapeAssText(string $text): string
    {
        $clean = str_replace(["\r\n", "\r", "\n"], ' ', trim($text));

        return str_replace(['\\', '{', '}'], ['\\\\', '\{', '\}'], $clean);
    }
}
