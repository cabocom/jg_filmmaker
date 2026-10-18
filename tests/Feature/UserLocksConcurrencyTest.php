<?php

namespace Tests\Feature;

use App\Livewire\Studio\ScriptEditor;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\JgAgente\AgentClientService;
use App\Services\ScriptPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class UserLocksConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_transition_to_structured_preserves_concurrent_pinned_terms_added_during_ai_call(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'Un hombre solitario camina bajo la lluvia.',
            'user_locks' => [
                'pinned_terms' => ['hombre' => 'detective'],
            ],
            'stage' => 'raw',
            'lock_version' => 1,
        ]);

        // Mockear AgentClientService para simular una mutación concurrente en user_locks mientras corre la IA
        $mockAgent = Mockery::mock(AgentClientService::class);
        $mockAgent->shouldReceive('structureTreatment')
            ->once()
            ->andReturnUsing(function () use ($block) {
                // Simulación de acción concurrente de otro proceso / pestaña:
                // El usuario añade otro pinned_term mientras la IA procesa
                DB::table('script_blocks')
                    ->where('id', $block->id)
                    ->update([
                        'user_locks' => json_encode([
                            'pinned_terms' => [
                                'hombre' => 'detective',
                                'lluvia' => 'tormenta eléctrica',
                            ],
                        ]),
                        'lock_version' => 2,
                    ]);

                return 'El detective camina bajo la lluvia torrencial.';
            });

        $mockAgent->shouldReceive('getLastTelemetry')
            ->andReturn(['tokens' => 150]);

        $pipeline = new ScriptPipelineService($mockAgent);
        $result = $pipeline->transitionToStructured($block);

        $fresh = $block->fresh();
        $this->assertSame('structured', $fresh->stage);
        $this->assertGreaterThan(1, $fresh->lock_version);

        // El pinned_term añadido concurrentemente ('lluvia' => 'tormenta eléctrica') NO debe perderse
        $this->assertArrayHasKey('lluvia', $fresh->user_locks['pinned_terms'] ?? []);
        $this->assertSame('tormenta eléctrica', $fresh->user_locks['pinned_terms']['lluvia']);
        $this->assertSame('detective', $fresh->user_locks['pinned_terms']['hombre']);
    }

    public function test_transition_to_structured_aborts_text_overwrite_if_stage_was_locked_concurrently(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $originalStructured = 'Texto bloqueado manualmente por el autor.';
        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'Boceto inicial.',
            'structured_text' => $originalStructured,
            'user_locks' => [],
            'stage' => 'raw',
            'lock_version' => 1,
        ]);

        $mockAgent = Mockery::mock(AgentClientService::class);
        $mockAgent->shouldReceive('structureTreatment')
            ->once()
            ->andReturnUsing(function () use ($block) {
                // Simular que el usuario activa el candado de 'structured' mientras la IA aún está computando
                DB::table('script_blocks')
                    ->where('id', $block->id)
                    ->update([
                        'user_locks' => json_encode([
                            'locked_fields' => ['structured'],
                        ]),
                        'lock_version' => 2,
                    ]);

                return 'Texto generado por la IA que debe ser descartado.';
            });

        $mockAgent->shouldReceive('getLastTelemetry')->andReturn(null);

        $pipeline = new ScriptPipelineService($mockAgent);
        $pipeline->transitionToStructured($block);

        $fresh = $block->fresh();
        // Se debe haber respetado el texto original porque el candado se activó concurrentemente
        $this->assertSame($originalStructured, $fresh->structured_text);
        $this->assertTrue($fresh->isStageLocked('structured'));
    }

    public function test_save_block_rejects_update_when_lock_version_collides_optimistic_locking(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'Texto original',
            'lock_version' => 1,
        ]);

        $component = Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('startEditBlock', $block->id)
            ->set('editRawText', 'Texto modificado por el usuario A');

        // Simular que entre que el usuario A abrió la edición y guardó, otro proceso incrementó la versión
        $block->update([
            'raw_text' => 'Texto modificado por proceso concurrente B',
            'lock_version' => 2,
        ]);

        // El usuario A intenta guardar
        $component->call('saveBlock')
            ->assertSee('El bloque fue modificado concurrentemente');

        // El texto en BD debe seguir siendo el del proceso concurrente B, no sobreescrito
        $fresh = $block->fresh();
        $this->assertSame('Texto modificado por proceso concurrente B', $fresh->raw_text);
        $this->assertSame(2, $fresh->lock_version);
    }

    public function test_save_block_from_drawer_rejects_update_when_lock_version_collides(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'Texto drawer',
            'lock_version' => 1,
        ]);

        $component = Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('openBlockDetails', $block->id)
            ->call('startEditingInDrawer')
            ->set('editRawText', 'Texto editado en drawer');

        // Simular mutación concurrente
        $block->update([
            'raw_text' => 'Texto modificado externamente',
            'lock_version' => 2,
        ]);

        $component->call('saveBlockFromDrawer')
            ->assertSee('El bloque fue modificado concurrentemente');

        $this->assertSame('Texto modificado externamente', $block->fresh()->raw_text);
    }

    public function test_toggle_stage_lock_mutates_atomically_under_pessimistic_lock(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'user_locks' => [
                'pinned_terms' => ['A' => 'B'],
                'locked_fields' => [],
            ],
            'lock_version' => 1,
        ]);

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('toggleStageLock', $block->id, 'structured');

        $fresh = $block->fresh();
        $this->assertTrue($fresh->isStageLocked('structured'));
        // Debe preservar los pinned_terms
        $this->assertArrayHasKey('A', $fresh->user_locks['pinned_terms']);
        $this->assertGreaterThan(1, $fresh->lock_version);
    }

    public function test_analyze_plot_holes_preserves_concurrent_user_locks_on_blocks(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'Texto con posible incoherencia.',
            'user_locks' => [
                'pinned_terms' => ['protagonista' => 'Carlos'],
                'locked_fields' => ['structured'],
            ],
            'lock_version' => 1,
        ]);

        $mockAgent = Mockery::mock(AgentClientService::class);
        $mockAgent->shouldReceive('auditPlotHoles')
            ->atLeast()->once()
            ->andReturn([
                'detected_holes' => [
                    [
                        'block_id' => $block->id,
                        'reason' => 'Incoherencia narrativa detectada en la acción.',
                    ],
                ],
            ]);
        $mockAgent->shouldReceive('getLastTelemetry')->andReturn(null);
        $this->app->instance(AgentClientService::class, $mockAgent);

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('analyzePlotHoles');

        $fresh = $block->fresh();
        $this->assertSame('Incoherencia narrativa detectada en la acción.', $fresh->plot_hole);
        $this->assertSame('raw', $fresh->getPlotHoleStage());
        // Preserva pinned_terms y locked_fields
        $this->assertArrayHasKey('protagonista', $fresh->user_locks['pinned_terms'] ?? []);
        $this->assertTrue($fresh->isStageLocked('structured'));
        $this->assertGreaterThan(1, $fresh->lock_version);
    }

    public function test_save_plot_hole_justification_mutates_locks_atomically(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'plot_hole' => 'El personaje aparece en dos sitios a la vez.',
            'user_locks' => [
                'plot_hole_stage' => 'raw',
                'pinned_terms' => ['Carlos' => 'Javier'],
            ],
            'lock_version' => 1,
        ]);

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('openJustifyModal', $block->id)
            ->set('justificationText', 'Es un hermano gemelo que no se había presentado.')
            ->call('savePlotHoleJustification');

        $fresh = $block->fresh();
        $this->assertNull($fresh->plot_hole);
        $this->assertNull($fresh->getPlotHoleStage());
        $this->assertTrue($fresh->hasJustifiedPlotHoles());
        $justified = $fresh->getJustifiedPlotHoles();
        $this->assertCount(1, $justified);
        $this->assertSame('Es un hermano gemelo que no se había presentado.', $justified[0]['justification']);
        $this->assertArrayHasKey('Carlos', $fresh->user_locks['pinned_terms']);
        $this->assertGreaterThan(1, $fresh->lock_version);
    }
}
