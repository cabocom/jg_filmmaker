<?php

namespace Tests\Feature;

use App\Livewire\Studio\ScriptEditor;
use App\Models\AgentRequestLog;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\JgAgente\AgentClientService;
use App\Services\ScriptPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class UserLocksTelemetryExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_sanitizes_user_locks_sent_to_agent_client_omitting_telemetry(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'Elena entra al almacén abandonado.',
            'user_locks' => [
                'pinned_terms' => ['Elena' => 'ELENA REYES'],
                'locked_fields' => [],
                'ai_telemetry' => [
                    'structured' => ['massive_prompt' => str_repeat('A', 5000)],
                ],
            ],
            'stage' => 'raw',
        ]);

        $mockAgent = Mockery::mock(AgentClientService::class);
        $mockAgent->shouldReceive('structureTreatment')
            ->once()
            ->withArgs(function ($rawText, $characters, $location, $userLocks) {
                // Verificar que el payload hacia el agente NO contiene ai_telemetry
                $this->assertArrayNotHasKey('ai_telemetry', $userLocks);
                $this->assertArrayHasKey('pinned_terms', $userLocks);
                $this->assertSame('ELENA REYES', $userLocks['pinned_terms']['Elena']);

                return true;
            })
            ->andReturn('ELENA REYES accede cautelosamente al almacén.');

        $mockAgent->shouldReceive('getLastTelemetry')->andReturn([
            'provider' => 'groq',
            'model' => 'llama-3.3-70b-versatile',
            'tokens' => 200,
            'user_locks' => ['pinned_terms' => ['Elena' => 'ELENA REYES']],
        ]);

        $pipeline = new ScriptPipelineService($mockAgent);
        $pipeline->transitionToStructured($block);

        $fresh = $block->fresh();
        // Verificar que user_locks en BD NO almacena la telemetría pesada
        $this->assertArrayNotHasKey('ai_telemetry', $fresh->user_locks);
        $this->assertArrayHasKey('pinned_terms', $fresh->user_locks);
    }

    public function test_agent_telemetry_persisted_in_agent_request_logs_table_meta(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'El detective busca las pistas.',
            'user_locks' => [],
            'stage' => 'raw',
        ]);

        $mockAgent = Mockery::mock(AgentClientService::class);
        $mockAgent->shouldReceive('structureTreatment')
            ->once()
            ->andReturn('El detective examina minuciosamente el suelo.');

        $mockAgent->shouldReceive('getLastTelemetry')->andReturn([
            'provider' => 'groq',
            'model' => 'llama-3.3-70b-versatile',
            'status' => 'SUCCESS',
            'duration_ms' => 450,
            'user_locks' => [
                'pinned_terms' => ['detective' => 'Carlos'],
                'ai_telemetry' => ['recursive' => 'must_be_stripped'],
            ],
        ]);

        $pipeline = new ScriptPipelineService($mockAgent);
        $pipeline->transitionToStructured($block);

        // Verificar que se creó el registro en agent_request_logs con meta
        $log = AgentRequestLog::where('script_block_id', $block->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertIsArray($log->meta);
        $this->assertSame('groq', $log->meta['provider']);
        $this->assertSame('llama-3.3-70b-versatile', $log->meta['model']);
        // Verificar que ai_telemetry recursivo fue purgado dentro de meta
        $this->assertArrayNotHasKey('ai_telemetry', $log->meta['user_locks'] ?? []);
    }

    public function test_open_ai_trace_reads_telemetry_from_agent_request_logs(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'user_locks' => [],
            'stage' => 'structured',
        ]);

        AgentRequestLog::create([
            'project_id' => $project->id,
            'script_block_id' => $block->id,
            'tool_name' => 'treatment_structuring',
            'input_tokens' => 120,
            'output_tokens' => 80,
            'total_tokens' => 200,
            'estimated_cost_usd' => 0.0001,
            'meta' => [
                'tool' => 'treatment_structuring',
                'stage' => 'structured',
                'provider' => 'groq',
                'model' => 'llama-3.3-70b-versatile',
                'duration_ms' => 520,
                'status' => 'SUCCESS',
                'output' => 'Tratamiento estructurado canónico.',
                'user_locks' => [],
            ],
        ]);

        $component = Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('openAiTrace', $block->id, 'structured');

        $traceData = $component->get('aiTraceData');
        $this->assertSame('treatment_structuring', $traceData['tool']);
        $this->assertSame('structured', $traceData['stage']);
        $this->assertSame('groq', $traceData['provider']);
        $this->assertTrue($component->get('showAiTraceModal'));
    }
}
