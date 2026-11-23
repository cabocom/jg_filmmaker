<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

final class ShotSubtitleGenerator
{
    private const int WORDS_PER_CUE = 10;

    /**
     * Genera un archivo de subtítulos en formato SubRip (.srt).
     */
    public function generateSrt(string $text, float $durationSeconds, string $outputPath): string
    {
        $duration = max(1.0, $durationSeconds);
        $cues = $this->segmentText($text);
        $totalCues = count($cues);
        $cueDuration = $duration / $totalCues;

        $lines = [];
        for ($i = 0; $i < $totalCues; $i++) {
            $start = $i * $cueDuration;
            $end = ($i + 1) * $cueDuration;
            $index = $i + 1;

            $lines[] = (string) $index;
            $lines[] = sprintf('%s --> %s', $this->formatSrtTime($start), $this->formatSrtTime($end));
            $lines[] = $cues[$i];
            $lines[] = '';
        }

        $this->ensureDirectoryExists($outputPath);
        file_put_contents($outputPath, implode("\n", $lines));

        return $outputPath;
    }

    /**
     * Genera un archivo de subtítulos Advanced SubStation Alpha (.ass) con estilo personalizado.
     */
    public function generateAss(string $text, float $durationSeconds, string $outputPath, bool $verticalShort = false): string
    {
        $duration = max(1.0, $durationSeconds);
        $cues = $this->segmentText($text);
        $totalCues = count($cues);
        $cueDuration = $duration / $totalCues;

        $resX = $verticalShort ? 1080 : 1920;
        $resY = $verticalShort ? 1920 : 1080;
        $fontSize = $verticalShort ? 72 : 48;
        $marginV = $verticalShort ? 240 : 60;

        $header = $this->buildAssHeader($resX, $resY, $fontSize, $marginV);
        $events = ['[Events]', 'Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text'];

        for ($i = 0; $i < $totalCues; $i++) {
            $start = $i * $cueDuration;
            $end = ($i + 1) * $cueDuration;
            $events[] = sprintf(
                'Dialogue: 0,%s,%s,Default,,0,0,0,,%s',
                $this->formatAssTime($start),
                $this->formatAssTime($end),
                $this->escapeAssText($cues[$i])
            );
        }

        $this->ensureDirectoryExists($outputPath);
        file_put_contents($outputPath, $header."\n".implode("\n", $events)."\n");

        return $outputPath;
    }

    /**
     * Segmenta el texto en bloques de palabras legibles.
     *
     * @return list<string>
     */
    private function segmentText(string $text): array
    {
        $clean = trim((string) preg_replace('/\s+/', ' ', $text));
        if ($clean === '') {
            return ['...'];
        }

        $words = explode(' ', $clean);
        $chunks = array_chunk($words, self::WORDS_PER_CUE);

        return array_map(static fn (array $chunk): string => implode(' ', $chunk), $chunks);
    }

    /**
     * Formatea segundos a tiempo SRT (HH:MM:SS,mmm).
     */
    private function formatSrtTime(float $seconds): string
    {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(fmod($seconds, 3600) / 60);
        $secs = (int) floor(fmod($seconds, 60));
        $millis = (int) round(($seconds - floor($seconds)) * 1000);

        if ($millis >= 1000) {
            $millis = 999;
        }

        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $millis);
    }

    /**
     * Formatea segundos a tiempo ASS (H:MM:SS.cc).
     */
    private function formatAssTime(float $seconds): string
    {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(fmod($seconds, 3600) / 60);
        $secs = (int) floor(fmod($seconds, 60));
        $centis = (int) round(($seconds - floor($seconds)) * 100);

        if ($centis >= 100) {
            $centis = 99;
        }

        return sprintf('%d:%02d:%02d.%02d', $hours, $minutes, $secs, $centis);
    }

    private function buildAssHeader(int $resX, int $resY, int $fontSize, int $marginV): string
    {
        return implode("\n", [
            '[Script Info]',
            'Title: Broadcast Subtitles',
            'ScriptType: v4.00+',
            'WrapStyle: 0',
            'ScaledBorderAndShadow: yes',
            'YCbCr Matrix: TV.709',
            "PlayResX: {$resX}",
            "PlayResY: {$resY}",
            '',
            '[V4+ Styles]',
            'Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding',
            "Style: Default,Arial,{$fontSize},&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,0,0,1,3,1,2,20,20,{$marginV},1",
            '',
        ]);
    }

    private function escapeAssText(string $text): string
    {
        return str_replace(['{', '}', "\n"], ['', '', '\N'], $text);
    }

    private function ensureDirectoryExists(string $filePath): void
    {
        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
