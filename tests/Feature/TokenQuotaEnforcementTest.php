<?php

namespace Tests\Feature;

use App\Exceptions\QuotaExceededException;
use App\Livewire\Studio\ScriptEditor;
use App\Models\AgentRequestLog;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\AgentTelemetryService;
use App\Services\ScriptPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TokenQuotaEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Scene $scene;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'daily_token_limit' => 500,
        ]);
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->scene = Scene::factory()->create(['project_id' => $this->project->id]);
    }

    public function test_structure_block_is_rejected_when_user_has_no_daily_tokens_available(): void
    {
        // Consumir todo el cupo diario creando logs de telemetría previos
        AgentRequestLog::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'total_tokens' => 500,
            'created_at' => now(),
        ]);

        $this->assertEquals(0, $this->user->getTokensAvailableToday());

        $block = ScriptBlock::factory()->create([
            'scene_id' => $this->scene->id,
            'raw_text' => 'Texto bruto sin estructurar',
            'stage' => 'raw',
        ]);

        Http::fake();

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->call('structureBlock', $block->id)
            ->assertSee('Has alcanzado tu cupo diario de tokens de IA');

        Http::assertNothingSent();
        $this->assertEquals('raw', $block->fresh()->stage);
    }

    public function test_format_block_to_screenplay_is_rejected_when_quota_exceeded(): void
    {
        AgentRequestLog::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'total_tokens' => 500,
            'created_at' => now(),
        ]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $this->scene->id,
            'structured_text' => 'Tratamiento estructurado',
            'screenplay_content' => null,
            'stage' => 'structured',
        ]);

        Http::fake();

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->call('formatBlockToScreenplay', $block->id)
            ->assertSee('Has alcanzado tu cupo diario de tokens de IA');

        Http::assertNothingSent();
        $this->assertNull($block->fresh()->screenplay_content);
    }

    public function test_breakdown_block_shots_is_rejected_when_quota_exceeded(): void
    {
        AgentRequestLog::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'total_tokens' => 500,
            'created_at' => now(),
        ]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $this->scene->id,
            'screenplay_content' => "INT. SALON - DIA\nJUAN\nHola.",
            'stage' => 'screenplay',
        ]);

        Http::fake();

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->call('breakdownBlockShots', $block->id)
            ->assertSee('Has alcanzado tu cupo diario de tokens de IA');

        Http::assertNothingSent();
        $this->assertEmpty($block->fresh()->shots);
    }

    public function test_prompt_analyze_plot_holes_blocks_modal_when_required_tokens_exceed_available(): void
    {
        // Dejar solo 10 tokens disponibles (de 500)
        AgentRequestLog::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'total_tokens' => 490,
            'created_at' => now(),
        ]);

        ScriptBlock::factory()->create([
            'scene_id' => $this->scene->id,
            'screenplay_content' => str_repeat('Un guion bastante largo con mucho texto descriptivo y dialogos. ', 30),
        ]);

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->call('promptAnalyzePlotHoles')
            ->assertSet('showPlotHoleConfirmModal', false)
            ->assertSee('Saldo insuficiente de tokens para auditar el proyecto');
    }

    public function test_pipeline_service_throws_quota_exceeded_exception_directly(): void
    {
        AgentRequestLog::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'total_tokens' => 500,
            'created_at' => now(),
        ]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $this->scene->id,
            'raw_text' => 'Texto bruto',
        ]);

        $pipeline = app(ScriptPipelineService::class);

        $this->expectException(QuotaExceededException::class);
        $this->expectExceptionMessage('Has alcanzado tu cupo diario de tokens de IA');

        $pipeline->transitionToStructured($block);
    }

    public function test_token_consumption_is_indelibly_linked_to_user_across_project_transfers(): void
    {
        $telemetry = app(AgentTelemetryService::class);

        // Registro de consumo inicial para el usuario 1
        $log = $telemetry->record(
            projectId: $this->project->id,
            scriptBlockId: null,
            toolName: 'treatment_structuring',
            inputPayload: 'Texto de prueba',
            outputPayload: 'Resultado de prueba',
            telemetry: ['usage' => ['prompt_tokens' => 150, 'completion_tokens' => 50]]
        );

        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertEquals(200, $this->user->getTokensUsedToday());

        // Transferir el proyecto a un segundo usuario
        $secondUser = User::factory()->create(['daily_token_limit' => 1000]);
        $this->project->update(['user_id' => $secondUser->id]);

        // El consumo del primer usuario se mantiene en 200 y NO se resetea ni se transfiere
        $this->assertEquals(200, $this->user->getTokensUsedToday());
        // El nuevo dueño del proyecto inicia con 0 tokens consumidos
        $this->assertEquals(0, $secondUser->getTokensUsedToday());
    }
}
