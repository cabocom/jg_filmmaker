<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Studio\AuditBibleEntityUsageAction;
use App\Livewire\Studio\ScriptEditor;
use App\Models\Character;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\JgAgente\AgentClientService;
use App\Services\TextToSpeechService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AuditCharacterBatchAndTtsAudioCompleteCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_character_usages_does_zero_llm_calls_when_name_does_not_appear_in_script(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);

        ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'Una persecución en coche sin personajes conocidos.',
        ]);

        $char = Character::factory()->create([
            'project_id' => $project->id,
            'name' => 'FANTASMA',
        ]);

        $mockClient = Mockery::mock(AgentClientService::class);
        $mockClient->shouldNotReceive('auditCharacterUsage');

        $action = new AuditBibleEntityUsageAction;
        $usages = $action->auditCharacterUsages($project, $char, $mockClient);

        $this->assertEmpty($usages);
    }

    public function test_audit_character_usages_batches_all_matches_into_a_single_llm_call_instead_of_n_calls(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);

        for ($i = 1; $i <= 5; $i++) {
            ScriptBlock::factory()->create([
                'scene_id' => $scene->id,
                'block_order' => $i,
                'screenplay_content' => "ROBERTO\nDiálogo número {$i} del personaje recurrente.",
            ]);
        }

        $char = Character::factory()->create([
            'project_id' => $project->id,
            'name' => 'ROBERTO',
        ]);

        $mockClient = Mockery::mock(AgentClientService::class);
        // Debe llamarse exactamente una vez en batch, NO 5 veces
        $mockClient->shouldReceive('auditCharacterUsage')
            ->once()
            ->with('ROBERTO', Mockery::on(fn ($snippets) => is_array($snippets) && count($snippets) === 5))
            ->andReturn([
                'character_name' => 'ROBERTO',
                'in_use' => true,
                'appearances_count' => 5,
                'summary' => 'Personaje activo en 5 bloques.',
            ]);
        $mockClient->shouldReceive('getLastTelemetry')->andReturn(null);

        $action = new AuditBibleEntityUsageAction;
        $usages = $action->auditCharacterUsages($project, $char, $mockClient);

        $this->assertCount(5, $usages);
        $this->assertEquals('ESC. 1 · BLOQUE 1', $usages[0]);
    }

    public function test_audit_character_usages_caches_result_for_subsequent_calls(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);

        ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'screenplay_content' => "ANA\nHola mundo.",
        ]);

        $char = Character::factory()->create([
            'project_id' => $project->id,
            'name' => 'ANA',
        ]);

        $mockClient = Mockery::mock(AgentClientService::class);
        $mockClient->shouldReceive('auditCharacterUsage')
            ->once()
            ->andReturn(['in_use' => true]);
        $mockClient->shouldReceive('getLastTelemetry')->andReturn(null);

        $action = new AuditBibleEntityUsageAction;

        // Primera llamada: invoca a IA
        $firstResult = $action->auditCharacterUsages($project, $char, $mockClient);

        // Segunda llamada inmediata (ej. confirmDelete): debe devolver desde caché sin re-invocar IA
        $secondResult = $action->auditCharacterUsages($project, $char, $mockClient);

        $this->assertEquals($firstResult, $secondResult);
    }

    public function test_is_project_audio_complete_is_cached_and_invalidates_on_script_block_change(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);

        $tts = new TextToSpeechService;

        // Sin bloques locutables -> false
        $this->assertFalse($tts->isProjectAudioComplete($project));

        $cacheKey = "project:{$project->id}:audio_complete:1.00";
        $this->assertTrue(Cache::has($cacheKey));

        // Al crear o guardar un ScriptBlock, la caché debe invalidarse
        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'stage' => 'raw',
            'raw_text' => 'Texto locutable nuevo',
        ]);

        $this->assertFalse(Cache::has($cacheKey), 'La caché de audio_complete debe invalidarse al guardar un ScriptBlock');

        // Evaluar de nuevo para rellenar caché
        $tts->isProjectAudioComplete($project);
        $this->assertTrue(Cache::has($cacheKey));

        // Al eliminar el bloque, la caché debe invalidarse nuevamente
        $block->delete();
        $this->assertFalse(Cache::has($cacheKey), 'La caché de audio_complete debe invalidarse al eliminar un ScriptBlock');
    }

    public function test_script_editor_does_not_expose_orphan_destructive_import_endpoints(): void
    {
        $this->assertFalse(method_exists(ScriptEditor::class, 'openImportModal'));
        $this->assertFalse(method_exists(ScriptEditor::class, 'processImportFile'));
        $this->assertFalse(method_exists(ScriptEditor::class, 'confirmAndExecuteDestructiveImport'));

        $editor = new ScriptEditor;
        $this->assertFalse(property_exists($editor, 'confirmDestructiveImport'));
        $this->assertFalse(property_exists($editor, 'showImportModal'));
    }
}
