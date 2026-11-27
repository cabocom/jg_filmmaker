<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Models\BroadcastJob;
use App\Services\TextToSpeechService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

final class IntroCardGenerator
{
    private const int FALLBACK_DURATION_SECONDS = 3;

    public function __construct(
        private readonly ?TextToSpeechService $ttsService = null,
    ) {}

    /**
     * Genera la pantalla de inicio con fondo blanco, texto negro, locución del título y 2s de pausa.
     *
     * @param  array<string, mixed>  $artifacts
     */
    public function generate(BroadcastJob $job, array $artifacts, string $tempDir): string
    {
        $title = trim((string) ($job->title ?? ''));
        $spoken = $title !== '' ? $title.'.' : BroadcastSeriesHelper::seriesNameForFormat($job->format).'.';

        return $this->renderTitleScreen(
            job: $job,
            artifacts: $artifacts,
            tempDir: $tempDir,
            baseName: 'intro_card',
            mainText: $title !== '' ? $title : BroadcastSeriesHelper::seriesNameForFormat($job->format),
            spokenText: $spoken,
            padSeconds: 2.0,
        );
    }

    /**
     * Genera la pantalla de cierre con fondo blanco, texto negro y locución de despedida.
     *
     * @param  array<string, mixed>  $artifacts
     */
    public function generateOutro(BroadcastJob $job, array $artifacts, string $tempDir): string
    {
        $closingText = $this->resolveClosingText($job->format);

        return $this->renderTitleScreen(
            job: $job,
            artifacts: $artifacts,
            tempDir: $tempDir,
            baseName: 'outro_card',
            mainText: $closingText,
            spokenText: $closingText,
            padSeconds: 1.0,
        );
    }

    public function resolveClosingText(?string $format): string
    {
        return match (strtolower(trim((string) $format))) {
            'pill', 'pildora' => 'Esta ha sido una píldora tecnológica escogida por Javier Gálvez y elaborada con Inteligencia Artificial.',
            'news', 'noticia' => 'Este ha sido un boletín informativo elaborado con Inteligencia Artificial.',
            'debate' => 'Este ha sido un debate elaborado con Inteligencia Artificial.',
            default => 'Esta ha sido una breve biografía escogida por Javier Gálvez y elaborada con Inteligencia Artificial.',
        };
    }

    /**
     * Construye un guion ASS UTF-8 con fondo blanco y letras negras usando DejaVu Sans.
     *
     * @param  array<string, mixed>  $artifacts
     */
    public function buildIntroAssContent(
        BroadcastJob $job,
        array $artifacts,
        int $width = 1920,
        int $height = 1080,
        ?string $overrideMainText = null,
        float $durationSeconds = self::FALLBACK_DURATION_SECONDS,
    ): string {
        $series = mb_strtoupper(BroadcastSeriesHelper::seriesNameForFormat($job->format), 'UTF-8');
        $mainText = trim((string) ($overrideMainText ?? $job->title ?? ''));
        $sources = is_array($job->sources) ? $job->sources : [];
        $sourceUrl = trim((string) ($sources[0] ?? ''));
        $visualCredit = trim((string) ($artifacts['visual_credit'] ?? ''));
        $footer = implode(' · ', array_filter([$sourceUrl, $visualCredit], static fn (string $v): bool => $v !== ''));

        $scale = $width / 1920;
        $seriesSize = (int) round(34 * $scale);
        $titleSize = mb_strlen($mainText, 'UTF-8') > 65 ? (int) round(48 * $scale) : (int) round(64 * $scale);
        $footerSize = (int) round(26 * $scale);
        $marginX = (int) round(140 * $scale);

        $end = $this->formatAssTime(max(1.0, $durationSeconds));
        $lines = [
            '[Script Info]',
            'Title: Broadcast Title Screen',
            'ScriptType: v4.00+',
            'WrapStyle: 0',
            'ScaledBorderAndShadow: yes',
            'YCbCr Matrix: TV.709',
            "PlayResX: {$width}",
            "PlayResY: {$height}",
            '',
            '[V4+ Styles]',
            'Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding',
            "Style: Series,DejaVu Sans,{$seriesSize},&H00695547,&H000000FF,&H00FFFFFF,&H00FFFFFF,-1,0,0,0,100,100,2,0,1,0,0,7,{$marginX},{$marginX},".(int) round($height * 0.18).',1',
            "Style: Title,DejaVu Sans,{$titleSize},&H00000000,&H000000FF,&H00FFFFFF,&H00FFFFFF,-1,0,0,0,100,100,0,0,1,0,0,4,{$marginX},{$marginX},0,1",
            "Style: Footer,DejaVu Sans,{$footerSize},&H00695547,&H000000FF,&H00FFFFFF,&H00FFFFFF,0,0,0,0,100,100,0,0,1,0,0,1,{$marginX},{$marginX},".(int) round($height * 0.10).',1',
            '',
            '[Events]',
            'Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text',
            'Dialogue: 0,0:00:00.00,'.$end.',Series,,0,0,0,,'.$this->escapeAssText($series),
            'Dialogue: 0,0:00:00.00,'.$end.',Title,,0,0,0,,'.$this->escapeAssText($mainText),
        ];

        if ($footer !== '') {
            $lines[] = 'Dialogue: 0,0:00:00.00,'.$end.',Footer,,0,0,0,,'.$this->escapeAssText($footer);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, mixed>  $artifacts
     */
    private function renderTitleScreen(
        BroadcastJob $job,
        array $artifacts,
        string $tempDir,
        string $baseName,
        string $mainText,
        string $spokenText,
        float $padSeconds,
    ): string {
        File::ensureDirectoryExists($tempDir);

        $is720p = (($artifacts['resolution_profile'] ?? '1080p30') === '720p30');
        $width = $is720p ? 1280 : 1920;
        $height = $is720p ? 720 : 1080;

        $audioPath = $this->synthesizeCardAudio($job, $artifacts, $spokenText);
        $speechDuration = $audioPath !== null ? $this->probeAudioDuration($audioPath) : 0.0;
        $totalDuration = $audioPath !== null
            ? max(2.0, round($speechDuration + $padSeconds, 2))
            : (float) self::FALLBACK_DURATION_SECONDS;

        $assPath = "{$tempDir}/{$baseName}.ass";
        $videoPath = "{$tempDir}/{$baseName}.mp4";

        File::put($assPath, $this->buildIntroAssContent($job, $artifacts, $width, $height, $mainText, $totalDuration));

        $escapedAss = str_replace(['\\', ':', "'"], ['/', '\\:', "\\'"], $assPath);
        $preset = $is720p ? ['-preset', 'veryfast'] : [];
        $durStr = number_format($totalDuration, 2, '.', '');

        $audioInput = $audioPath !== null
            ? ['-i', $audioPath, '-af', sprintf('apad=pad_dur=%.2F,aresample=48000', $padSeconds)]
            : ['-f', 'lavfi', '-t', $durStr, '-i', 'anullsrc=channel_layout=stereo:sample_rate=48000'];

        $cmd = [
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', "color=c=0xffffff:s={$width}x{$height}:d={$durStr}:r=30",
            ...$audioInput,
            '-vf', "subtitles={$escapedAss}",
            '-t', $durStr,
            '-r', '30', '-fps_mode', 'cfr', '-video_track_timescale', '30000',
            '-c:v', 'libx264', '-profile:v', 'high', '-level', '4.1', '-pix_fmt', 'yuv420p', ...$preset,
            '-c:a', 'aac', '-ar', '48000', '-ac', '2',
            '-movflags', '+faststart', $videoPath,
        ];

        $result = Process::timeout(60)->run($cmd);
        if (! $result->successful()) {
            throw new RuntimeException("Fallo al generar {$baseName}.mp4 para el job {$job->id}: ".$result->errorOutput());
        }

        return $videoPath;
    }

    /**
     * @param  array<string, mixed>  $artifacts
     */
    private function synthesizeCardAudio(BroadcastJob $job, array $artifacts, string $spokenText): ?string
    {
        if (trim($spokenText) === '') {
            return null;
        }

        try {
            $tts = $this->ttsService ?? app(TextToSpeechService::class);
            $voice = isset($artifacts['tts_voice']) && is_string($artifacts['tts_voice']) && trim($artifacts['tts_voice']) !== ''
                ? trim($artifacts['tts_voice'])
                : null;
            $path = $tts->synthesize($spokenText, $voice, 1.0, $job->project);

            return is_string($path) && $path !== '' && is_file($path) ? $path : null;
        } catch (Throwable $e) {
            Log::warning('IntroCardGenerator: fallo al sintetizar audio de pantalla de título, usando silencio.', [
                'broadcast_job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function probeAudioDuration(string $audioPath): float
    {
        $probe = Process::timeout(15)->run([
            'ffprobe', '-v', 'error', '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1', $audioPath,
        ]);

        if ($probe->successful()) {
            $val = (float) trim($probe->output());
            if ($val > 0.1) {
                return $val;
            }
        }

        return (float) self::FALLBACK_DURATION_SECONDS;
    }

    private function formatAssTime(float $seconds): string
    {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(fmod($seconds, 3600) / 60);
        $secs = (int) floor(fmod($seconds, 60));
        $centis = min(99, (int) round(($seconds - floor($seconds)) * 100));

        return sprintf('%d:%02d:%02d.%02d', $hours, $minutes, $secs, $centis);
    }

    private function escapeAssText(string $text): string
    {
        $clean = str_replace(["\r\n", "\r", "\n"], ' ', trim($text));

        return str_replace(['\\', '{', '}'], ['\\\\', '\{', '\}'], $clean);
    }
}
