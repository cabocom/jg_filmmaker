<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Services\JgAgente\AgentClientService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Smalot\PdfParser\Parser as PdfParser;

class ScriptImportService
{
    public function __construct(
        protected ?AgentClientService $agentClient = null
    ) {}

    /**
     * Extrae el texto legible de un archivo subido (PDF o TXT).
     */
    public function extractTextFromFile(UploadedFile $file): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mimeType = (string) $file->getMimeType();

        if ($extension === 'pdf' || str_contains($mimeType, 'pdf')) {
            return $this->extractTextFromPdf($file->getRealPath());
        }

        if (in_array($extension, ['txt', 'fountain', 'text'], true) || str_contains($mimeType, 'text')) {
            $content = file_get_contents($file->getRealPath());
            if ($content === false) {
                throw new InvalidArgumentException('No se pudo leer el contenido del archivo de texto.');
            }

            return $this->normalizeEncoding($content);
        }

        throw new InvalidArgumentException("Formato de archivo no soportado (.{$extension}). Solo se admiten archivos .txt y .pdf.");
    }

    /**
     * Extrae texto de un archivo PDF usando Smalot PdfParser (con fallback).
     */
    public function extractTextFromPdf(string $filePath): string
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();

            if (! empty(trim($text))) {
                return $this->normalizeEncoding($text);
            }
        } catch (\Throwable $e) {
            Log::warning('Smalot PdfParser falló al extraer texto: '.$e->getMessage());
        }

        // Fallback a pdftotext si está instalado en el sistema
        $escapedPath = escapeshellarg($filePath);
        $output = @shell_exec("pdftotext {$escapedPath} - 2>/dev/null");
        if ($output && ! empty(trim($output))) {
            return $this->normalizeEncoding($output);
        }

        throw new InvalidArgumentException('No se pudo extraer texto legible del PDF. Asegúrate de que no sea un PDF escaneado como imagen o protegido con contraseña.');
    }

    /**
     * Normaliza la codificación a UTF-8 y unifica saltos de línea.
     */
    public function normalizeEncoding(string $text): string
    {
        $encoding = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'WINDOWS-1252', 'ASCII'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $text = (string) mb_convert_encoding($text, 'UTF-8', $encoding);
        }

        // Unificar saltos de línea a \n
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim($text);
    }

    /**
     * Analiza el texto dramático identificando escenas, localizaciones, personajes y bloques.
     *
     * @return array{
     *     scenes_count: int,
     *     blocks_count: int,
     *     characters: list<string>,
     *     locations: list<string>,
     *     scenes: list<array{
     *         scene_order: int,
     *         slugline: string,
     *         location_name: string,
     *         time_of_day: string,
     *         blocks: list<array{
     *             block_order: int,
     *             raw_text: string,
     *             screenplay_content: string,
     *             type: string
     *         }>
     *     }>,
     *     preview: array{
     *         first_slugline: ?string,
     *         snippet: string
     *     }
     * }
     */
    public function parseScreenplay(string $text, bool $useAi = false, ?Project $project = null): array
    {
        $normalizedText = $this->normalizeEncoding($text);
        if (empty($normalizedText)) {
            throw new InvalidArgumentException('El archivo de guión está vacío.');
        }

        $lines = explode("\n", $normalizedText);

        $scenes = [];
        $uniqueCharacters = [];
        $uniqueLocations = [];

        $currentScene = null;
        $currentBlocks = [];
        $currentBlockLines = [];
        $currentBlockType = 'action';
        $sceneOrder = 1;
        $blockOrder = 1;

        // Patrón canónico de Sluglines cinematográficas
        // INT. LOCALIZACION - DIA / EXT. CALLE - NOCHE 1 / INT/EXT. COCHE - CONTINUO
        $sluglineRegex = '/^(?:(?:\d+[\.\-\s]+|\bESCENA\s+\d+[:\s\-\.]*)\s*)?(INT\.|EXT\.|INT\/EXT\.|INT\.\/EXT\.|I\/E\.|EST\.)\s+(.+?)(?:(?:\s*[\-\–]\s*|\s+)(D[IÍ]A|NOCHE|TARDE|MAÑANA|CONTINUO|CONTINUA|MOMENTOS DESPU[EÉ]S|SUNSET|DAWN|NIGHT|DAY))?(?:\s*[\-\–\s]+(\d+))?$/iu';

        // Patrón para nombres de personajes antes de diálogo (mayúsculas de 2 a 35 caracteres)
        $characterRegex = '/^[A-ZÁÉÍÓÚÑ0-9\s\.\-_]{2,35}(?:\s*\(.*?\))?$/u';

        // Filtro de falsos positivos en nombres de personajes (transiciones de montaje)
        $transitionWords = ['CORTE A:', 'FUNDIDO A:', 'DISUELVE A:', 'FADE IN:', 'FADE OUT:', 'SMASH CUT:', 'MATCH CUT:'];

        $flushCurrentBlock = function () use (&$currentBlocks, &$currentBlockLines, &$currentBlockType, &$blockOrder) {
            if (! empty($currentBlockLines)) {
                $blockRawText = trim(implode("\n", $currentBlockLines));
                if (! empty($blockRawText)) {
                    $currentBlocks[] = [
                        'block_order' => $blockOrder++,
                        'raw_text' => $blockRawText,
                        'screenplay_content' => $blockRawText,
                        'type' => $currentBlockType,
                    ];
                }
                $currentBlockLines = [];
                $currentBlockType = 'action';
            }
        };

        $flushCurrentScene = function () use (&$scenes, &$currentScene, &$currentBlocks, $flushCurrentBlock) {
            $flushCurrentBlock();
            if ($currentScene !== null) {
                // Si la escena no tenía bloques, le creamos al menos un bloque con la acción
                if (empty($currentBlocks)) {
                    $currentBlocks[] = [
                        'block_order' => 1,
                        'raw_text' => $currentScene['slugline'],
                        'screenplay_content' => $currentScene['slugline'],
                        'type' => 'action',
                    ];
                }
                $currentScene['blocks'] = $currentBlocks;
                $scenes[] = $currentScene;
                $currentBlocks = [];
            }
        };

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Detección de nueva escena (Slugline)
            if (preg_match($sluglineRegex, $trimmedLine, $matches)) {
                $flushCurrentScene();

                $prefix = strtoupper(trim($matches[1]));
                $locationPart = trim($matches[2] ?? 'GENERAL');
                $timePart = strtoupper(trim($matches[3] ?? 'DÍA'));

                // Normalizar momentos del día
                if (str_contains($timePart, 'DIA') || str_contains($timePart, 'DÍA') || $timePart === 'DAY') {
                    $timePart = 'DÍA';
                } elseif (str_contains($timePart, 'NOCHE') || $timePart === 'NIGHT') {
                    $timePart = 'NOCHE';
                } elseif (str_contains($timePart, 'TARDE')) {
                    $timePart = 'TARDE';
                } elseif (str_contains($timePart, 'MAÑANA')) {
                    $timePart = 'MAÑANA';
                } else {
                    $timePart = 'CONTINUO';
                }

                // Saneamiento de localización
                $locationPartClean = preg_replace('/\s+/', ' ', $locationPart);
                $locationPartClean = trim(rtrim($locationPartClean, '-–. '));
                if (empty($locationPartClean)) {
                    $locationPartClean = 'GENERAL';
                }

                $slugline = "{$prefix} {$locationPartClean} - {$timePart} {$sceneOrder}";
                $uniqueLocations[] = $locationPartClean;

                $blockOrder = 1;
                $currentScene = [
                    'scene_order' => $sceneOrder++,
                    'slugline' => $slugline,
                    'location_name' => $locationPartClean,
                    'time_of_day' => $timePart,
                    'blocks' => [],
                ];

                continue;
            }

            // Si aún no se ha detectado ninguna slugline inicial, creamos la primera escena por defecto
            if ($currentScene === null && ! empty($trimmedLine)) {
                $currentScene = [
                    'scene_order' => $sceneOrder++,
                    'slugline' => 'INT. GENERAL - DÍA 1',
                    'location_name' => 'GENERAL',
                    'time_of_day' => 'DÍA',
                    'blocks' => [],
                ];
                $uniqueLocations[] = 'GENERAL';
            }

            if ($currentScene === null) {
                continue;
            }

            // Detección de personajes (línea en mayúsculas no vacía y sin minúsculas)
            if (
                ! empty($trimmedLine) &&
                preg_match($characterRegex, $trimmedLine) &&
                ! in_array($trimmedLine, $transitionWords, true) &&
                mb_strtoupper($trimmedLine, 'UTF-8') === $trimmedLine
            ) {
                // Extraer el nombre limpio de personaje sin parentéticas
                $rawCharName = preg_replace('/\s*\(.*?\)\s*/', '', $trimmedLine);
                $cleanCharName = trim((string) $rawCharName);

                if (mb_strlen($cleanCharName) >= 2 && ! is_numeric($cleanCharName)) {
                    $uniqueCharacters[] = $cleanCharName;
                }

                // Empezar bloque de diálogo
                $flushCurrentBlock();
                $currentBlockType = 'dialogue';
                $currentBlockLines[] = $trimmedLine;

                continue;
            }

            // Separación de bloques en párrafos vacíos si el bloque actual supera cierto tamaño
            if (empty($trimmedLine)) {
                if (count($currentBlockLines) >= 4) {
                    $flushCurrentBlock();
                }

                continue;
            }

            $currentBlockLines[] = $line;
        }

        // Consolidar la última escena
        $flushCurrentScene();

        // Limpiar duplicados preservando orden
        $uniqueCharacters = array_values(array_unique(array_filter($uniqueCharacters)));
        $uniqueLocations = array_values(array_unique(array_filter($uniqueLocations)));

        $totalBlocks = 0;
        foreach ($scenes as $sc) {
            $totalBlocks += count($sc['blocks']);
        }

        return [
            'scenes_count' => count($scenes),
            'blocks_count' => $totalBlocks,
            'characters' => $uniqueCharacters,
            'locations' => $uniqueLocations,
            'scenes' => $scenes,
            'preview' => [
                'first_slugline' => $scenes[0]['slugline'] ?? null,
                'snippet' => mb_substr($normalizedText, 0, 800),
            ],
        ];
    }

    /**
     * Importa atómicamente los datos parseados dentro de un proyecto.
     * Si $destructive es true, limpia previamente escenas, bloques y planos existentes.
     */
    public function importIntoProject(Project $project, array $parsedData, bool $destructive = true): void
    {
        if (empty($parsedData['scenes'])) {
            throw new InvalidArgumentException('No hay escenas válidas para importar.');
        }

        DB::transaction(function () use ($project, $parsedData, $destructive) {
            if ($destructive) {
                // Eliminar escenas existentes en cascada (dispara eventos que eliminan tomas físicas)
                foreach ($project->scenes as $existingScene) {
                    $existingScene->delete();
                }
            }

            // Registrar/Actualizar localizaciones en la Biblia
            $locationMap = [];
            foreach ($parsedData['locations'] as $locName) {
                $location = Location::firstOrCreate(
                    [
                        'project_id' => $project->id,
                        'name' => $locName,
                    ],
                    [
                        'description' => "Localización importada del guion: {$locName}",
                        'lighting_notes' => 'Importado automáticamente',
                    ]
                );
                $locationMap[$locName] = $location->id;
            }

            // Registrar/Actualizar personajes en la Biblia
            foreach ($parsedData['characters'] as $charName) {
                Character::firstOrCreate(
                    [
                        'project_id' => $project->id,
                        'name' => $charName,
                    ],
                    [
                        'bio' => "Personaje importado del guion: {$charName}",
                        'costume_notes' => 'Pendiente de definir',
                    ]
                );
            }

            // Offset de orden si no es destructivo
            $baseSceneOrder = $destructive ? 0 : (int) $project->scenes()->max('scene_order');

            // Insertar escenas y bloques
            foreach ($parsedData['scenes'] as $sceneData) {
                $locId = $locationMap[$sceneData['location_name']] ?? null;

                $scene = Scene::create([
                    'project_id' => $project->id,
                    'location_id' => $locId,
                    'scene_order' => $baseSceneOrder + $sceneData['scene_order'],
                    'slugline' => $sceneData['slugline'],
                    'time_of_day' => $sceneData['time_of_day'],
                    'description' => "Escena {$sceneData['slugline']} importada del guion original.",
                ]);

                foreach ($sceneData['blocks'] as $blockData) {
                    ScriptBlock::create([
                        'scene_id' => $scene->id,
                        'block_order' => $blockData['block_order'],
                        'raw_text' => $blockData['raw_text'],
                        'screenplay_content' => $blockData['screenplay_content'],
                        'structured_text' => null,
                        'stage' => 'raw',
                        'user_locks' => ['locked_fields' => []],
                    ]);
                }
            }
        });
    }
}
