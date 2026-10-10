<?php

namespace Tests\Feature;

use App\Livewire\Studio\ScriptEditor;
use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\ScriptPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ScriptPipelineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_transitions_block_to_structured_using_project_bible_and_locks(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create(['project_id' => $project->id, 'name' => 'Javier']);
        $location = Location::factory()->create(['project_id' => $project->id, 'name' => 'BAR CENTRAL', 'type' => 'INT']);

        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'location_id' => $location->id,
        ]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => "Javi entra por la puerta.\nMira alrededor con cautela.",
            'user_locks' => [
                'pinned_terms' => ['Javi' => 'Javier'],
            ],
            'stage' => 'raw',
        ]);

        $pipeline = app(ScriptPipelineService::class);
        $updatedBlock = $pipeline->transitionToStructured($block);

        $this->assertSame('structured', $updatedBlock->stage);
        $this->assertNotEmpty($updatedBlock->structured_text);
        // Debe contener el nombre fijado por el candado en mayúsculas ('JAVIER' en lugar de 'Javi')
        $this->assertStringContainsString('JAVIER', $updatedBlock->structured_text);
        // No debe forzar prefijos de localización en el tratamiento (la localización pertenece a la escena y slugline)
        $this->assertStringNotContainsString('En BAR CENTRAL', $updatedBlock->structured_text);
        $this->assertStringContainsString('entra por la puerta', $updatedBlock->structured_text);
    }

    public function test_pipeline_transitions_block_to_screenplay_format(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create(['project_id' => $project->id, 'name' => 'Javier']);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'Javier dice que no hay vuelta atrás.',
            'structured_text' => "Javier mira al frente.\nJavier: No hay vuelta atrás.",
            'user_locks' => [],
            'stage' => 'structured',
        ]);

        $pipeline = app(ScriptPipelineService::class);
        $updatedBlock = $pipeline->transitionToScreenplay($block);

        $this->assertSame('screenplay', $updatedBlock->stage);
        $this->assertNotEmpty($updatedBlock->screenplay_content);
        $this->assertStringContainsString('JAVIER', $updatedBlock->screenplay_content);
        $this->assertStringContainsString('No hay vuelta atrás.', $updatedBlock->screenplay_content);
    }

    public function test_pipeline_generates_shots_breakdown_and_creates_shot_and_take_entities(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'screenplay_content' => "INT. BAR CENTRAL - NOCHE\n\nJAVIER apoya el vaso.\n\nJAVIER\nYa están aquí.",
            'stage' => 'screenplay',
        ]);

        $pipeline = app(ScriptPipelineService::class);
        $shots = $pipeline->generateShotsBreakdown($block);

        $this->assertGreaterThanOrEqual(1, $shots->count());
        $this->assertSame('shots', $block->fresh()->stage);

        // Verificar que se crearon en la base de datos
        foreach ($shots as $shot) {
            $this->assertDatabaseHas('shots', [
                'id' => $shot->id,
                'scene_id' => $scene->id,
                'script_block_id' => $block->id,
            ]);

            // Debe haber creado al menos una toma base
            $this->assertGreaterThanOrEqual(1, $shot->takes()->count());
        }
    }

    public function test_manual_override_is_recorded_in_user_locks_schema(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'structured_text' => 'Javier observa la calle.',
            'user_locks' => [],
        ]);

        $pipeline = app(ScriptPipelineService::class);
        $pipeline->recordManualOverride($block, 'structured', 'Javier observa la calle.', 'Javier vigila la calle con nerviosismo.');

        $freshBlock = $block->fresh();
        $userLocks = $freshBlock->user_locks;

        $this->assertIsArray($userLocks);
        $this->assertContains('structured', $userLocks['locked_fields']);
        $this->assertCount(1, $userLocks['manual_diffs']);
        $this->assertSame('Javier observa la calle.', $userLocks['manual_diffs'][0]['original_ai']);
        $this->assertSame('Javier vigila la calle con nerviosismo.', $userLocks['manual_diffs'][0]['user_override']);
    }

    public function test_livewire_script_editor_triggers_pipeline_actions_and_updates_state(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'Una sombra cruza el callejón.',
            'stage' => 'raw',
        ]);

        $testable = Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('structureBlock', $block->id);

        $this->assertSame('structured', $block->fresh()->stage);
        $this->assertNotEmpty($block->fresh()->structured_text);

        $testable->call('formatBlockToScreenplay', $block->id);
        $this->assertSame('screenplay', $block->fresh()->stage);
        $this->assertNotEmpty($block->fresh()->screenplay_content);

        $testable->call('breakdownBlockShots', $block->id);
        $this->assertSame('shots', $block->fresh()->stage);
        $this->assertDatabaseHas('shots', [
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
        ]);
    }

    public function test_pipeline_handles_string_or_double_encoded_user_locks_gracefully(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'El detective camina bajo la lluvia.',
            'stage' => 'raw',
        ]);

        // Simular valor serializado string o malformado en base de datos
        DB::table('script_blocks')
            ->where('id', $block->id)
            ->update(['user_locks' => '"{}"']);

        $block = ScriptBlock::findOrFail($block->id);
        $pipeline = app(ScriptPipelineService::class);

        // No debe lanzar TypeError y debe completar la transición
        $updatedBlock = $pipeline->transitionToStructured($block);

        $this->assertSame('structured', $updatedBlock->stage);
        $this->assertIsArray($updatedBlock->user_locks);
    }
}
