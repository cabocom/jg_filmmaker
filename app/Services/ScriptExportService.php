<?php

namespace App\Services;

use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;

class ScriptExportService
{
    /**
     * Genera el documento PDF del guion completo del proyecto con formato estándar de la industria.
     */
    public function generateScreenplayPdf(Project $project): DomPdfWrapper
    {
        $project->load(['scenes.scriptBlocks', 'user']);

        $scenesData = [];

        foreach ($project->scenes->sortBy('scene_order') as $scene) {
            $blocksData = [];

            foreach ($scene->scriptBlocks->sortBy('block_order') as $block) {
                $rawContent = ! empty(trim((string) $block->screenplay_content))
                    ? $block->screenplay_content
                    : (! empty(trim((string) $block->structured_text)) ? $block->structured_text : $block->raw_text);

                $parsedLines = $this->parseBlockContent((string) $rawContent);

                $blocksData[] = [
                    'id' => $block->id,
                    'order' => $block->block_order,
                    'elements' => $parsedLines,
                ];
            }

            $scenesData[] = [
                'id' => $scene->id,
                'order' => $scene->scene_order,
                'slugline' => $scene->slugline,
                'blocks' => $blocksData,
            ];
        }

        $pdf = Pdf::loadView('pdf.screenplay', [
            'project' => $project,
            'scenes' => $scenesData,
            'exportedAt' => now()->translatedFormat('d \d\e F \d\e Y'),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', false);
        $pdf->setOption('defaultFont', 'Courier');

        return $pdf;
    }

    /**
     * Parsea las líneas de un bloque en elementos tipográficos de guion cinematográfico:
     * - scene_heading: Encabezados INT./EXT.
     * - character: Nombres de personaje en mayúsculas
     * - parenthetical: Acotaciones (entre paréntesis)
     * - dialogue: Parlamento del personaje
     * - action: Descripción de acción cinematográfica
     *
     * @return list<array{type: string, text: string}>
     */
    public function parseBlockContent(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
        $elements = [];
        $isExpectingDialogue = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $isExpectingDialogue = false;

                continue;
            }

            // 1. Encabezado de escena (Slugline)
            if (preg_match('/^(?:INT|EXT|INT\/EXT|EXT\/INT)\b/iu', $trimmed)) {
                $elements[] = ['type' => 'scene_heading', 'text' => mb_strtoupper($trimmed, 'UTF-8')];
                $isExpectingDialogue = false;

                continue;
            }

            // 2. Acotación / Parenthetical: (acotación)
            if (preg_match('/^\([^)]+\)$/u', $trimmed)) {
                $elements[] = ['type' => 'parenthetical', 'text' => $trimmed];
                $isExpectingDialogue = true;

                continue;
            }

            // 3. Personaje con acotación pegada: "PERSONAJE (acotación)"
            if (preg_match('/^([A-ZÁÉÍÓÚÑ0-9\s\.\-\']{2,40})\s*(\([^)]+\))$/u', $trimmed, $mCharPar)) {
                $elements[] = ['type' => 'character', 'text' => mb_strtoupper(trim($mCharPar[1]), 'UTF-8')];
                $elements[] = ['type' => 'parenthetical', 'text' => trim($mCharPar[2])];
                $isExpectingDialogue = true;

                continue;
            }

            // 4. Nombre de personaje en mayúsculas
            if (! $isExpectingDialogue && preg_match('/^[A-ZÁÉÍÓÚÑ0-9\s\.\-\']{2,40}$/u', $trimmed) && ! preg_match('/[.!?]$/u', $trimmed)) {
                $elements[] = ['type' => 'character', 'text' => mb_strtoupper($trimmed, 'UTF-8')];
                $isExpectingDialogue = true;

                continue;
            }

            // 5. Diálogo (si la línea anterior era personaje o acotación)
            if ($isExpectingDialogue) {
                $elements[] = ['type' => 'dialogue', 'text' => $trimmed];
                $isExpectingDialogue = false;

                continue;
            }

            // 6. Por defecto: Acción / Descripción
            $elements[] = ['type' => 'action', 'text' => $trimmed];
            $isExpectingDialogue = false;
        }

        return $elements;
    }
}
