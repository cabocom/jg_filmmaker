<?php

namespace Tests\Feature;

use App\Services\JgAgente\AgentClientService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ContractParityTest extends TestCase
{
    private array $goldenVectors;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.jg_agente.mock', true);

        $fixturePath = base_path('tests/fixtures/contract_golden_vectors.json');
        $this->goldenVectors = json_decode(file_get_contents($fixturePath), true);
    }

    public function test_treatment_structuring_matches_golden_contract_spec(): void
    {
        $service = app(AgentClientService::class);
        $spec = $this->goldenVectors['treatment_structuring'];

        $result = $service->callTool('treatment_structuring', $spec['payload']);

        $this->assertTrue($result['success']);
        $this->assertSame('structured', $result['metadata']['stage']);

        foreach ($spec['expected_substrings'] as $substring) {
            $this->assertStringContainsString($substring, $result['output']);
        }
    }

    public function test_screenplay_formatter_matches_golden_contract_spec(): void
    {
        $service = app(AgentClientService::class);
        $spec = $this->goldenVectors['screenplay_formatter'];

        $result = $service->callTool('screenplay_formatter', $spec['payload']);

        $this->assertTrue($result['success']);
        $this->assertSame('screenplay', $result['metadata']['stage']);

        foreach ($spec['expected_substrings'] as $substring) {
            $this->assertStringContainsStringIgnoringCase($substring, $result['output']);
        }
    }

    public function test_shot_breakdown_detector_matches_golden_contract_spec(): void
    {
        $service = app(AgentClientService::class);
        $spec = $this->goldenVectors['shot_breakdown_detector'];

        $result = $service->callTool('shot_breakdown_detector', $spec['payload']);

        $this->assertTrue($result['success']);
        $this->assertSame('shots', $result['metadata']['stage']);

        foreach ($spec['expected_substrings'] as $substring) {
            $this->assertStringContainsString($substring, $result['output']);
        }
    }

    public function test_plot_hole_detector_matches_golden_contract_spec(): void
    {
        $service = app(AgentClientService::class);
        $spec = $this->goldenVectors['plot_hole_detector'];

        $result = $service->callTool('plot_hole_detector', $spec['payload']);

        $this->assertTrue($result['success']);
        $this->assertContains($result['metadata']['stage'], ['plot_holes', 'audit']);
        $this->assertArrayHasKey('detected_holes', $result['metadata']);
    }
}
