<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Project;
use App\Services\ScriptImportService;
use Illuminate\Http\UploadedFile;

final readonly class ImportScriptAction
{
    /**
     * Parsea un archivo de guion subido.
     *
     * @return array<string, mixed>
     */
    public function parseFile(UploadedFile $file, bool $useAi, Project $project, ?ScriptImportService $importService = null): array
    {
        $importService ??= app(ScriptImportService::class);
        $rawText = $importService->extractTextFromFile($file);

        return $importService->parseScreenplay($rawText, $useAi, $project);
    }

    /**
     * Ejecuta la importación destructiva sobreescribiendo el contenido del proyecto.
     *
     * @param  array<string, mixed>  $parsedData
     */
    public function executeDestructiveImport(Project $project, array $parsedData, ?ScriptImportService $importService = null): void
    {
        $importService ??= app(ScriptImportService::class);
        $importService->importIntoProject($project, $parsedData, true);
    }
}
