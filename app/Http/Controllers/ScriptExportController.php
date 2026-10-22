<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ScriptExportService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ScriptExportController extends Controller
{
    /**
     * Descarga el guión completo del proyecto en formato PDF con formato cinematográfico estándar.
     */
    public function exportPdf(Project $project, ScriptExportService $exportService): Response
    {
        Gate::authorize('view', $project);

        $pdf = $exportService->generateScreenplayPdf($project);
        $filename = Str::slug($project->title ?: 'guion').'-guion.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
