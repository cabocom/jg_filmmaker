<?php

namespace Tests\Feature;

use App\Livewire\Studio\ScriptEditor;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use App\Services\JgAgente\AgentClientService;
use App\Services\ScriptPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class UserLocksPreservationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Scene $scene;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->scene = Scene::factory()->create(['project_id' => $this->project->id]);
    }

    public function test_regenerating_structured_archives_existing_manual_diffs(): void
    {
        $block = ScriptBlock::factory()->create([
            'scene_id' => $this->scene->id,
            'structured_text' => 'Texto original antes de editar',
            'screenplay_content' => 'INT. SALON - DIA',
            'user_locks' => [
                'manual_diffs' => [
                    [
                        'target' => 'structured',
                        'original' => 'Texto original',
                        'modified' => 'Texto editado manualmente',
                    ],
                    [
                        'target' => 'screenplay',
                        'original' => 'INT.',
                        'modified' => 'EXT.',
                    ],
                ],
                'locked_fields' => [],
            ],
        ]);

        Http::fake([
            '*/api/v1/tools/*' => Http::response([
                'success' => true,
                'output' => 'Nuevo texto estructurado generado por IA.',
            ], 200),
        ]);

        $pipeline = app(ScriptPipelineService::class);
        $pipeline->transitionToStructured($block);

        $block->refresh();

        // El diff de 'structured' se archivó y ya no está en manual_diffs activos; el de 'screenplay' sigue activo
        $this->assertCount(1, $block->user_locks['manual_diffs']);
        $this->assertEquals('screenplay', $block->user_locks['manual_diffs'][0]['target']);

        $this->assertNotEmpty($block->user_locks['archived_diffs']);
        $this->assertCount(1, $block->user_locks['archived_diffs']);
        $this->assertEquals('structured', $block->user_locks['archived_diffs'][0]['target']);
        $this->assertArrayHasKey('archived_at', $block->user_locks['archived_diffs'][0]);
    }

    public function test_shots_breakdown_pipeline_returns_existing_without_calling_ai_when_shots_stage_is_locked(): void
    {
        $block = ScriptBlock::factory()->create([
            'scene_id' => $this->scene->id,
            'screenplay_content' => "INT. ESTUDIO - NOCHE\nJUAN\nHola.",
            'user_locks' => [
                'locked_fields' => ['shots'],
            ],
        ]);

        $shot = Shot::factory()->create([
            'scene_id' => $this->scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'description' => 'Plano original bloqueado',
        ]);

        Http::fake();

        $pipeline = app(ScriptPipelineService::class);
        $shots = $pipeline->generateShotsBreakdown($block);

        Http::assertNothingSent();
        $this->assertCount(1, $shots);
        $this->assertEquals($shot->id, $shots->first()->id);
    }

    public function test_script_editor_flashes_error_when_generating_shots_for_locked_block(): void
    {
        $block = ScriptBlock::factory()->create([
            'scene_id' => $this->scene->id,
            'screenplay_content' => "INT. ESTUDIO - NOCHE\nJUAN\nHola.",
            'user_locks' => [
                'locked_fields' => ['shots'],
            ],
        ]);

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->call('breakdownBlockShots', $block->id)
            ->assertSee('está protegido (🔒) por candado');
    }

    public function test_shots_breakdown_preserves_shots_with_recorded_takes_even_if_unlocked(): void
    {
        $block = ScriptBlock::factory()->create([
            'scene_id' => $this->scene->id,
            'screenplay_content' => "INT. ESTUDIO - NOCHE\nJUAN\nHola mundo.",
            'user_locks' => [
                'locked_fields' => [],
            ],
        ]);

        // Shot con Take grabada
        $shotWithTake = Shot::factory()->create([
            'scene_id' => $this->scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'PM',
            'description' => 'Plano con tomas grabadas no alterable',
        ]);
        $take = Take::factory()->create([
            'shot_id' => $shotWithTake->id,
            'take_number' => 1,
        ]);

        Http::fake([
            '*/api/v1/tools/*' => Http::response([
                'success' => true,
                'output' => "--- shot 1 (PP) ---\nIntento de sobreescritura IA\n\n--- shot 2 (PG) ---\nNuevo plano 2 generado por IA",
                'metadata' => [
                    'shots' => [
                        [
                            'shot_number' => 1,
                            'shot_type' => 'PP',
                            'description' => 'Intento de sobreescritura IA',
                        ],
                        [
                            'shot_number' => 2,
                            'shot_type' => 'PG',
                            'description' => 'Nuevo plano 2 generado por IA',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $pipeline = app(ScriptPipelineService::class);
        $pipeline->generateShotsBreakdown($block);

        // Plano 1 no fue alterado destructivamente en su descripción ni tipo
        $shotWithTake->refresh();
        $this->assertEquals('PM', $shotWithTake->shot_type);
        $this->assertEquals('Plano con tomas grabadas no alterable', $shotWithTake->description);
        $this->assertDatabaseHas('takes', ['id' => $take->id]);

        // Plano 2 fue creado con su take inicial por defecto
        $this->assertDatabaseHas('shots', [
            'script_block_id' => $block->id,
            'shot_number' => 2,
            'description' => 'Nuevo plano 2 generado por IA',
        ]);
    }

    public function test_agent_client_service_replaces_only_first_matching_diff_occurrence(): void
    {
        $agentClient = app(AgentClientService::class);

        $text = 'HOLA mundo. HOLA universo. HOLA galaxia.';
        $locks = [
            'manual_diffs' => [
                [
                    'target' => 'screenplay',
                    'original' => 'HOLA',
                    'modified' => 'ADIOS',
                ],
            ],
        ];

        $result = $agentClient->applyUserLocks($text, $locks, 'screenplay');

        $this->assertEquals('ADIOS mundo. HOLA universo. HOLA galaxia.', $result);
    }
}
