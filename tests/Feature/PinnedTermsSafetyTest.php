<?php

namespace Tests\Feature;

use App\Livewire\Studio\ScriptEditor;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\JgAgente\AgentClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PinnedTermsSafetyTest extends TestCase
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

    public function test_pinned_terms_replaces_literally_without_evaluating_backreferences(): void
    {
        $agentClient = app(AgentClientService::class);

        $text = 'El coste del equipo es de coste_original y el modelo es modelo_antiguo.';
        $locks = [
            'pinned_terms' => [
                'coste_original' => '$100',
                'modelo_antiguo' => 'AT&T \1',
            ],
        ];

        $result = $agentClient->applyUserLocks($text, $locks, 'structured');

        $this->assertEquals('El coste del equipo es de $100 y el modelo es AT&T \1.', $result);
    }

    public function test_pinned_terms_matches_non_alphanumeric_and_symbolic_terms_properly(): void
    {
        $agentClient = app(AgentClientService::class);

        $text = 'Juan programa en C++ y tiene el puesto #1 del ranking.';
        $locks = [
            'pinned_terms' => [
                'C++' => 'Rust',
                '#1' => 'Primer Lugar',
            ],
        ];

        $result = $agentClient->applyUserLocks($text, $locks, 'structured');

        $this->assertEquals('Juan programa en Rust y tiene el puesto Primer Lugar del ranking.', $result);
    }

    public function test_pinned_terms_respects_max_length_limit(): void
    {
        $agentClient = app(AgentClientService::class);

        $overlongTerm = str_repeat('A', 85);
        $text = "Texto con {$overlongTerm} que no debe reemplazarse.";
        $locks = [
            'pinned_terms' => [
                $overlongTerm => 'CORTO',
            ],
        ];

        $result = $agentClient->applyUserLocks($text, $locks, 'structured');

        $this->assertEquals($text, $result);
    }

    public function test_script_editor_can_add_and_remove_pinned_term_via_ui(): void
    {
        $block = ScriptBlock::factory()->create([
            'scene_id' => $this->scene->id,
            'user_locks' => [
                'pinned_terms' => [],
            ],
        ]);

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->call('addPinnedTerm', $block->id, 'Javi', 'Javier')
            ->assertSee("Término fijo 'Javi' fijado como 'Javier'.");

        $block->refresh();
        $this->assertEquals('Javier', $block->user_locks['pinned_terms']['Javi']);

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->call('removePinnedTerm', $block->id, 'Javi')
            ->assertSee("Término fijo 'Javi' eliminado.");

        $block->refresh();
        $this->assertArrayNotHasKey('Javi', $block->user_locks['pinned_terms']);
    }

    public function test_add_pinned_term_validates_empty_and_overlong_terms(): void
    {
        $block = ScriptBlock::factory()->create([
            'scene_id' => $this->scene->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->call('addPinnedTerm', $block->id, '', 'Javier')
            ->assertSee('El término original y su sustituto no pueden estar vacíos.');

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->call('addPinnedTerm', $block->id, str_repeat('X', 85), 'Valido')
            ->assertSee('Los términos fijados no pueden exceder los 80 caracteres de longitud.');
    }
}
