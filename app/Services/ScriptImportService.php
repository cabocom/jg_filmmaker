<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Services\Import\ScreenplayParser;
use App\Services\Import\ScriptFileExtractor;
use App\Services\JgAgente\AgentClientService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScriptImportService
{
    protected ScriptFileExtractor $extractor;

    protected ScreenplayParser $parser;

    public function __construct(
        protected ?AgentClientService $agentClient = null,
        ?ScriptFileExtractor $extractor = null,
        ?ScreenplayParser $parser = null
    ) {
        $this->extractor = $extractor ?? new ScriptFileExtractor;
        $this->parser = $parser ?? new ScreenplayParser($this->extractor);
    }

    public function extractTextFromFile(UploadedFile $file): string
    {
        return $this->extractor->extractTextFromFile($file);
    }

    public function extractTextFromPdf(string $filePath): string
    {
        return $this->extractor->extractTextFromPdf($filePath);
    }

    public function normalizeEncoding(string $text): string
    {
        return $this->extractor->normalizeEncoding($text);
    }

    /**
     * @return array{scenes_count: int, blocks_count: int, characters: list<string>, locations: list<string>, scenes: list<array>, preview: array}
     */
    public function parseScreenplay(string $text, bool $useAi = false, ?Project $project = null): array
    {
        return $this->parser->parse($text);
    }

    public function importIntoProject(Project $project, array $parsedData, bool $destructive = true): void
    {
        if (empty($parsedData['scenes'])) {
            throw new InvalidArgumentException('No hay escenas válidas para importar.');
        }

        DB::transaction(function () use ($project, $parsedData, $destructive) {
            if ($destructive) {
                foreach ($project->scenes as $existingScene) {
                    $existingScene->delete();
                }
            }

            $locationMap = $this->syncLocations($project, $parsedData['locations'] ?? []);
            $this->syncCharacters($project, $parsedData['characters'] ?? []);

            $baseSceneOrder = $destructive ? 0 : (int) $project->scenes()->max('scene_order');
            $this->createScenesAndBlocks($project, $parsedData['scenes'], $locationMap, $baseSceneOrder);
        });
    }

    protected function syncLocations(Project $project, array $locations): array
    {
        $locationMap = [];
        foreach ($locations as $locName) {
            $location = Location::firstOrCreate(
                ['project_id' => $project->id, 'name' => $locName],
                ['description' => "Localización importada del guion: {$locName}", 'lighting_notes' => 'Importado automáticamente']
            );
            $locationMap[$locName] = $location->id;
        }

        return $locationMap;
    }

    protected function syncCharacters(Project $project, array $characters): void
    {
        foreach ($characters as $charName) {
            Character::firstOrCreate(
                ['project_id' => $project->id, 'name' => $charName],
                ['bio' => "Personaje importado del guion: {$charName}", 'costume_notes' => 'Pendiente de definir']
            );
        }
    }

    protected function createScenesAndBlocks(Project $project, array $scenes, array $locationMap, int $baseSceneOrder): void
    {
        foreach ($scenes as $sceneData) {
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
    }
}
