<?php

namespace Tests\Feature;

use App\Services\JgAgente\AgentClientService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AgentClientRobustnessTest extends TestCase
{
    public function test_call_tool_marks_agent_source_when_successful(): void
    {
        Config::set('services.jg_agente.mock', false);
        Config::set('services.jg_agente.secret', 'test-long-secret-key-for-robustness-check-1234');

        Http::fake([
            '*/api/v1/tools/treatment_structuring' => Http::response([
                'success' => true,
                'output' => 'Tratamiento estructurado por el agente.',
                'metadata' => ['stage' => 'structured'],
            ], 200),
        ]);

        $service = app(AgentClientService::class);
        $result = $service->callTool('treatment_structuring', ['raw_text' => 'Notas del autor']);

        $this->assertTrue($result['success']);
        $this->assertSame('agent', $result['source']);
        $this->assertFalse($result['degraded']);
        $this->assertSame('Tratamiento estructurado por el agente.', $result['output']);
    }

    public function test_call_tool_logs_warning_and_falls_back_when_jg_agente_returns_server_error(): void
    {
        Config::set('services.jg_agente.mock', false);
        Config::set('services.jg_agente.secret', 'test-long-secret-key-for-robustness-check-1234');

        Log::shouldReceive('warning')
            ->atLeast()->once();

        Http::fake([
            '*/api/v1/tools/treatment_structuring' => Http::response(['error' => 'Internal agent error'], 500),
        ]);

        $service = app(AgentClientService::class);
        $result = $service->callTool('treatment_structuring', ['raw_text' => 'Notas del autor']);

        $this->assertTrue($result['success']);
        $this->assertSame('deterministic_fallback', $result['source']);
        $this->assertTrue($result['degraded']);
    }

    public function test_call_tool_recognizes_success_false_from_agent_and_falls_back(): void
    {
        Config::set('services.jg_agente.mock', false);
        Config::set('services.jg_agente.secret', 'test-long-secret-key-for-robustness-check-1234');

        Log::shouldReceive('warning')
            ->atLeast()->once();

        Http::fake([
            '*/api/v1/tools/treatment_structuring' => Http::response([
                'success' => false,
                'error' => 'Validación de payload fallida',
            ], 200),
        ]);

        $service = app(AgentClientService::class);
        $result = $service->callTool('treatment_structuring', ['raw_text' => 'Notas del autor']);

        $this->assertTrue($result['success']);
        $this->assertSame('deterministic_fallback', $result['source']);
        $this->assertTrue($result['degraded']);
    }

    public function test_direct_llm_is_strictly_disabled_in_production(): void
    {
        $this->app['env'] = 'production';
        Config::set('services.jg_agente.mock', false);
        Config::set('services.jg_agente.secret', 'test-long-secret-key-for-robustness-check-1234');
        Config::set('services.llm.direct_enabled', true);
        Config::set('services.llm.api_key', 'gsk_sample_key_for_test');

        Http::fake([
            '*/api/v1/tools/*' => Http::response(['error' => 'Down'], 503),
            'https://api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'Bypassed LLM']]]], 200),
        ]);

        $service = app(AgentClientService::class);
        $result = $service->callTool('treatment_structuring', ['raw_text' => 'Texto de prueba']);

        // En producción nunca debe ir a direct_llm, sino al fallback determinista controlado
        $this->assertNotSame('direct_llm', $result['source']);
        $this->assertSame('deterministic_fallback', $result['source']);
        $this->assertTrue($result['degraded']);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'api.groq.com');
        });
    }
}
