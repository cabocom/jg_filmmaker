<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\JgAgente\AgentClientService;
use App\Services\Pipeline\BibleSyncService;
use App\Services\ScriptPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BatchPipelineOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transition_to_structured_skips_project_sync_when_sync_flag_is_false(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create(['project_id' => $project->id, 'name' => 'Sara']);
        $location = Location::factory()->create(['project_id' => $project->id, 'name' => 'COCINA']);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'location_id' => $location->id]);
        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'Sara observa el mapa.',
            'stage' => 'raw',
        ]);

        $mockBibleSync = Mockery::mock(BibleSyncService::class);
        // Cuando syncProjectContext = false, syncProjectCharacters NUNCA debe ser llamado
        $mockBibleSync->shouldNotReceive('syncProjectCharacters');
        $mockBibleSync->shouldReceive('inferCharactersFromText')
            ->once()
            ->withArgs(function ($text, $existingChars, $proj, $allowLlm) {
                // Debe llamarse con allowLlmExtraction = false para evitar llamada LLM redundante por bloque
                return $allowLlm === false;
            })
            ->andReturn([$character->toAgentContext()]);
        $mockBibleSync->shouldReceive('capitalizeCharacterNames')
            ->andReturn('Sara observa el mapa.');

        $pipeline = new ScriptPipelineService(
            agentClient: app(AgentClientService::class),
            bibleSync: $mockBibleSync
        );

        $result = $pipeline->transitionToStructured($block, syncProjectContext: false);
        $this->assertSame('structured', $result->stage);
    }

    public function test_transition_to_screenplay_skips_project_sync_when_sync_flag_is_false(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create(['project_id' => $project->id, 'name' => 'Marcos']);
        $location = Location::factory()->create(['project_id' => $project->id, 'name' => 'DESPACHO']);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'location_id' => $location->id]);
        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'Marcos cierra el expediente.',
            'structured_text' => "Marcos cierra el expediente.\nMarcos: Se acabó.",
            'stage' => 'structured',
        ]);

        $mockBibleSync = Mockery::mock(BibleSyncService::class);
        // Cuando syncProjectContext = false, syncProjectCharacters NUNCA debe ser llamado
        $mockBibleSync->shouldNotReceive('syncProjectCharacters');
        $mockBibleSync->shouldReceive('inferCharactersFromText')
            ->once()
            ->withArgs(function ($text, $existingChars, $proj, $allowLlm) {
                return $allowLlm === false;
            })
            ->andReturn([$character->toAgentContext()]);
        $mockBibleSync->shouldReceive('capitalizeCharacterNames')
            ->andReturn("MARCOS\nSe acabó.");

        $pipeline = new ScriptPipelineService(
            agentClient: app(AgentClientService::class),
            bibleSync: $mockBibleSync
        );

        $result = $pipeline->transitionToScreenplay($block, syncProjectContext: false);
        $this->assertSame('screenplay', $result->stage);
    }

    public function test_batch_run_full_pipeline_syncs_project_bible_only_once_for_multiple_blocks(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $location = Location::factory()->create(['project_id' => $project->id, 'name' => 'SALÓN']);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'location_id' => $location->id, 'slugline' => 'INT. SALÓN - NOCHE']);

        $block1 = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'Elena entra al salón.',
            'block_order' => 1,
            'stage' => 'raw',
        ]);
        $block2 = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'Elena busca las llaves.',
            'block_order' => 2,
            'stage' => 'raw',
        ]);
        $block3 = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'Elena suspira aliviada.',
            'block_order' => 3,
            'stage' => 'raw',
        ]);

        $mockBibleSync = Mockery::mock(BibleSyncService::class);
        // syncProjectCharacters debe llamarse exactamente UNA VEZ para todo el lote de 3 bloques
        $mockBibleSync->shouldReceive('syncProjectCharacters')
            ->once()
            ->withArgs(fn ($p) => $p->id === $project->id);

        // inferCharactersFromText en cada bloque debe ser invocado con allowLlmExtraction = false
        $mockBibleSync->shouldReceive('inferCharactersFromText')
            ->withArgs(fn ($text, $chars, $p, $allowLlm) => $allowLlm === false)
            ->andReturn([]);

        $mockBibleSync->shouldReceive('capitalizeCharacterNames')
            ->andReturnUsing(fn ($text) => $text);

        $pipeline = new ScriptPipelineService(
            agentClient: app(AgentClientService::class),
            bibleSync: $mockBibleSync
        );

        $blocks = collect([$block1, $block2, $block3]);
        $stats = $pipeline->batchRunFullPipeline($blocks);

        $this->assertSame(3, $stats['structured']);
        $this->assertSame(3, $stats['screenplay']);
        $this->assertSame(3, $stats['shots']);
        $this->assertEmpty($stats['errors']);
    }

    public function test_infer_characters_with_allow_llm_false_does_not_call_agent_client(): void
    {
        $mockAgentClient = Mockery::mock(AgentClientService::class);
        $mockAgentClient->shouldNotReceive('extractCharacters');

        $bibleSync = new BibleSyncService($mockAgentClient);

        // Con allowLlmExtraction = false, debe utilizar detección determinista sin llamar a extractCharacters
        $result = $bibleSync->inferCharactersFromText(
            text: "DANIEL entra en la habitación.\nDANIEL\nHola a todos.",
            existingCharacters: [],
            project: null,
            allowLlmExtraction: false
        );

        $this->assertNotEmpty($result);
        $this->assertSame('Daniel', $result[0]['name']);
    }
}
