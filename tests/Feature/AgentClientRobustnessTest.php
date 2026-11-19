<?php

declare(strict_types=1);

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

    public function test_call_tool_logs_warning_and_fails_closed_when_jg_agente_returns_server_error(): void
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

        $this->assertFalse($result['success']);
        $this->assertSame('agent', $result['source']);
        $this->assertFalse($result['degraded']);
        $this->assertStringContainsString('Error HTTP 500', $result['error']);
    }

    public function test_call_tool_recognizes_success_false_from_agent_and_fails_closed(): void
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

        $this->assertFalse($result['success']);
        $this->assertSame('agent', $result['source']);
        $this->assertFalse($result['degraded']);
        $this->assertStringContainsString('Validación de payload fallida', $result['error']);
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

        // En producción nunca debe ir a direct_llm, sino reportar el fallo del agente de forma estricta
        $this->assertFalse($result['success']);
        $this->assertNotSame('direct_llm', $result['source'] ?? '');

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'api.groq.com');
        });
    }
}
