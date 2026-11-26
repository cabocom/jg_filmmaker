<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

final readonly class MotionFilterBuilder
{
    /**
     * Construye el filtro ffmpeg full-bleed 16:9 con movimiento Ken Burns según el tipo de plano.
     */
    public function buildImageMotionFilter(
        string $shotType,
        float $durationSeconds,
        int $width,
        int $height,
        ?string $assPath = null
    ): string {
        $frames = max(30, (int) round($durationSeconds * 30));
        $scaleW = $width * 2;
        $scaleH = $height * 2;

        $zoomExpr = $this->resolveZoomExpression($shotType, $frames);

        $filter = sprintf(
            'scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d,zoompan=%s:d=%d:s=%dx%d:fps=30,format=yuv420p',
            $scaleW,
            $scaleH,
            $scaleW,
            $scaleH,
            $zoomExpr,
            $frames,
            $width,
            $height
        );

        if ($assPath !== null && trim($assPath) !== '') {
            $filter .= ',subtitles='.$this->escapeFilterPath($assPath);
        }

        return $filter;
    }

    /**
     * Construye el filtro ffmpeg full-bleed 16:9 para clips de vídeo sin padding.
     */
    public function buildVideoScaleFilter(int $width, int $height, ?string $assPath = null): string
    {
        $filter = sprintf(
            'fps=30,scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d',
            $width,
            $height,
            $width,
            $height
        );

        if ($assPath !== null && trim($assPath) !== '') {
            $filter .= ',subtitles='.$this->escapeFilterPath($assPath);
        }

        return $filter;
    }

    private function escapeFilterPath(string $path): string
    {
        return str_replace(['\\', ':', "'", ' '], ['\\\\', '\\:', "\\'", '\\ '], trim($path));
    }

    private function resolveZoomExpression(string $shotType, int $frames): string
    {
        return match (strtoupper(trim($shotType))) {
            'PG', 'WIDE' => "z='1.08':x='(iw-iw/zoom)*(on/{$frames})':y='ih/2-(ih/zoom/2)'",
            'PP', 'CLOSE_UP' => "z='min(zoom+0.0005,1.14)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'",
            default => "z='min(zoom+0.0003,1.08)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'",
        };
    }
}
