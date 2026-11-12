<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Project;
use App\Services\ScriptExportService;
use App\Services\TextToSpeechService;
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
     */
    public function exportMp3(Project $project, ?TextToSpeechService $tts = null): BinaryFileResponse
    {
        $tts ??= app(TextToSpeechService::class);
        $mp3Path = $tts->exportProjectToMp3($project);
        $filename = Str::slug($project->title ?: 'guion').'-locucion-completa.mp3';

        return response()->download($mp3Path, $filename, [
            'Content-Type' => 'audio/mpeg',
        ])->deleteFileAfterSend(true);
    }
}
