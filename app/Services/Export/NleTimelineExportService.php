<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Project;
use App\Models\Take;
use Illuminate\Database\Eloquent\Collection;

class NleTimelineExportService
{
    /**
     * Exporta la secuencia de tomas aprobadas al estándar CMX 3600 EDL (.edl).
     *
     * @param  Collection<int, Take>|null  $goodTakes
     */
    public function exportCmx3600Edl(Project $project, ?Collection $goodTakes = null, float $fps = 24.0): string
    {
        $takes = $goodTakes ?? $this->getProjectGoodTakes($project);
        $title = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $project->title) ?: 'ROUGH_CUT');

        $lines = [
            "TITLE: {$title}",
            'FCM: NON-DROP FRAME',
            '',
        ];

        // Estándar de la industria: La línea de tiempo de programa arranca en 01:00:00:00 (1 hora)
        $timelineOffsetSeconds = 3600.0;
        $eventIndex = 1;

        foreach ($takes as $take) {
            $durationSeconds = max(0.1, (float) ($take->technical_meta['duration_seconds'] ?? 5.0));

            $srcIn = $this->secondsToSmpte(0.0, $fps);
            $srcOut = $this->secondsToSmpte($durationSeconds, $fps);

            $recIn = $this->secondsToSmpte($timelineOffsetSeconds, $fps);
            $timelineOffsetSeconds += $durationSeconds;
            $recOut = $this->secondsToSmpte($timelineOffsetSeconds, $fps);

            $eventId = sprintf('%03d', $eventIndex);
            $lines[] = "{$eventId}  AX       AA/V  C        {$srcIn} {$srcOut} {$recIn} {$recOut}";

            $clipPath = $take->effectiveVideoPath() ?? "take_{$take->id}.mp4";
            $clipName = basename($clipPath);
            $lines[] = "* FROM CLIP NAME: {$clipName}";

            $scene = $take->shot?->scene;
            $sceneOrder = $scene?->scene_order ?? 1;
            $slugline = $scene?->slugline ?? 'ESCENA';
            $shotType = $take->shot?->shot_type ?? 'PM';
            $notes = $take->editorial_notes ? ' - '.trim((string) $take->editorial_notes) : '';

            $lines[] = "* COMMENT: [ESCENA {$sceneOrder} - {$slugline} - {$shotType}]{$notes}";
            $lines[] = '';

            $eventIndex++;
        }

        return implode("\r\n", $lines);
    }

    /**
     * Exporta la secuencia al formato profesional Apple Final Cut Pro XML v1.10 (.fcpxml).
     * Totalmente compatible con DaVinci Resolve Studio y Final Cut Pro.
     *
     * @param  Collection<int, Take>|null  $goodTakes
     */
    public function exportFcpxml(Project $project, ?Collection $goodTakes = null, float $fps = 24.0): string
    {
        $takes = $goodTakes ?? $this->getProjectGoodTakes($project);
        $frameDuration = sprintf('1/%ds', (int) round($fps));

        $totalDurationSeconds = 0.0;
        $assetElements = [];
        $clipElements = [];

        foreach ($takes as $index => $take) {
            $durationSeconds = max(0.1, (float) ($take->technical_meta['duration_seconds'] ?? 5.0));
            $frames = (int) round($durationSeconds * $fps);
            $durationAttr = sprintf('%d/%ds', $frames, (int) round($fps));
            $offsetAttr = sprintf('%d/%ds', (int) round($totalDurationSeconds * $fps), (int) round($fps));

            $assetId = "a{$take->id}";
            $clipPath = $take->effectiveVideoPath() ?? "media/projects/{$project->id}/takes/take_{$take->id}.mp4";
            $clipName = htmlspecialchars(basename($clipPath), ENT_XML1);

            $assetElements[] = "        <asset id=\"{$assetId}\" name=\"{$clipName}\" src=\"file://localhost/{$clipPath}\" duration=\"{$durationAttr}\" format=\"r1\" hasVideo=\"1\" hasAudio=\"1\"/>";

            $scene = $take->shot?->scene;
            $sceneOrder = $scene?->scene_order ?? 1;
            $shotType = $take->shot?->shot_type ?? 'PM';
            $markerValue = htmlspecialchars("[Escena {$sceneOrder} - {$shotType}] ".($take->editorial_notes ?? 'Toma aprobada'), ENT_XML1);

            $clipElements[] = "                    <asset-clip ref=\"{$assetId}\" offset=\"{$offsetAttr}\" name=\"{$clipName}\" duration=\"{$durationAttr}\">";
            $clipElements[] = "                        <marker start=\"0s\" duration=\"{$frameDuration}\" value=\"{$markerValue}\"/>";
            $clipElements[] = '                    </asset-clip>';

            $totalDurationSeconds += $durationSeconds;
        }

        $projectTitle = htmlspecialchars((string) $project->title, ENT_XML1);
        $totalSequenceDuration = sprintf('%d/%ds', (int) round($totalDurationSeconds * $fps), (int) round($fps));

        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<!DOCTYPE fcpxml>';
        $xml[] = '<fcpxml version="1.10">';
        $xml[] = '    <resources>';
        $xml[] = "        <format id=\"r1\" name=\"FFVideoFormat1080p24\" frameDuration=\"{$frameDuration}\" width=\"1920\" height=\"1080\"/>";
        $xml = array_merge($xml, $assetElements);
        $xml[] = '    </resources>';
        $xml[] = '    <library>';
        $xml[] = "        <event name=\"{$projectTitle} - Evento NLE\">";
        $xml[] = "            <project name=\"{$projectTitle} - Pre-corte\">";
        $xml[] = "                <sequence format=\"r1\" duration=\"{$totalSequenceDuration}\" tcStart=\"0s\">";
        $xml[] = '                    <spine>';
        $xml = array_merge($xml, $clipElements);
        $xml[] = '                    </spine>';
        $xml[] = '                </sequence>';
        $xml[] = '            </project>';
        $xml[] = '        </event>';
        $xml[] = '    </library>';
        $xml[] = '</fcpxml>';

        return implode("\n", $xml);
    }

    /**
     * Convierte segundos flotantes a código de tiempo SMPTE (HH:MM:SS:FF).
     */
    public function secondsToSmpte(float $seconds, float $fps = 24.0): string
    {
        $totalFrames = (int) round($seconds * $fps);
        $intFps = max(1, (int) round($fps));
        $frames = $totalFrames % $intFps;
        $totalSeconds = (int) floor($totalFrames / $intFps);
        $secs = $totalSeconds % 60;
        $mins = (int) floor($totalSeconds / 60) % 60;
        $hours = (int) floor($totalSeconds / 3600);

        return sprintf('%02d:%02d:%02d:%02d', $hours, $mins, $secs, $frames);
    }

    /**
     * @return Collection<int, Take>
     */
    protected function getProjectGoodTakes(Project $project): Collection
    {
        return Take::query()
            ->with(['shot.scene.location'])
            ->where('is_good_take', true)
            ->whereHas('shot.scene', fn ($q) => $q->where('project_id', $project->id))
            ->join('shots', 'takes.shot_id', '=', 'shots.id')
            ->join('scenes', 'shots.scene_id', '=', 'scenes.id')
            ->orderBy('scenes.scene_order')
            ->orderBy('shots.shot_number')
            ->orderBy('takes.take_number')
            ->select('takes.*')
            ->get();
    }
}
