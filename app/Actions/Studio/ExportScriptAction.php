<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Project;
use App\Services\ScriptExportService;
use App\Services\TextToSpeechService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ExportScriptAction
{
    /**
     * Genera la descarga en streaming del guion cinematográfico en PDF.
     */
    public function exportPdf(Project $project, ?ScriptExportService $exportService = null): StreamedResponse
    {
        $exportService ??= app(ScriptExportService::class);
        $pdf = $exportService->generateScreenplayPdf($project);
        $filename = Str::slug($project->title ?: 'guion').'-guion.pdf';

        return response()->streamDownload(fn () => print ($pdf->output()), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Concatena y comprime los audios TTS en un único MP3 descargable.
     * Reutiliza archivo pre-renderizado si existe o adquiere exclusión mutua.
     */
    public function exportMp3(Project $project, ?TextToSpeechService $tts = null): BinaryFileResponse
    {
        $relativeDest = "media/projects/{$project->id}/audio/screenplay_locucion.mp3";
        $disk = Storage::disk('local');
        $filename = Str::slug($project->title ?: 'guion').'-locucion-completa.mp3';

        if ($disk->exists($relativeDest)) {
            return response()->download($disk->path($relativeDest), $filename, [
                'Content-Type' => 'audio/mpeg',
            ]);
        }

        $lockKey = "ffmpeg_mp3_{$project->id}";
        $lock = Cache::lock($lockKey, 360);
        $lock->block(15);

        try {
            if ($disk->exists($relativeDest)) {
                return response()->download($disk->path($relativeDest), $filename, [
                    'Content-Type' => 'audio/mpeg',
                ]);
            }

            $tts ??= app(TextToSpeechService::class);
            $mp3Path = $tts->exportProjectToMp3($project);

            return response()->download($mp3Path, $filename, [
                'Content-Type' => 'audio/mpeg',
            ])->deleteFileAfterSend(true);
        } finally {
            $lock->release();
        }
    }
}
