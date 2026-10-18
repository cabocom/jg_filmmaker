<?php

namespace Tests\Feature;

use App\Services\JgAgente\AgentClientService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReDosMitigationTest extends TestCase
{
    public function test_format_screenplay_fallback_handles_adversarial_input_without_catastrophic_backtracking(): void
    {
        Config::set('services.jg_agente.mock', true);

        $service = app(AgentClientService::class);

        // Párrafo con repetición de enlaces y comas sin saltos para forzar backtracking si la regex no está acotada
        $adversarialParagraph = str_repeat('mientras tanto, y luego y tras eso ', 50).'dice que todo está en orden.';
        $adversarialText = implode("\n\n", array_fill(0, 10, $adversarialParagraph));

        $start = microtime(true);
        $result = $service->callTool('screenplay_formatter', [
            'structured_text' => $adversarialText,
        ]);
        $duration = microtime(true) - $start;

        $this->assertTrue($result['success']);
        $this->assertSame('deterministic_fallback', $result['source']);
        $this->assertLessThan(0.3, $duration, "El procesamiento tardó {$duration}s, posible vulnerabilidad ReDoS.");
    }

    public function test_format_screenplay_fallback_fast_path_skips_regex_when_no_verbs_present(): void
    {
        Config::set('services.jg_agente.mock', true);

        $service = app(AgentClientService::class);

        // Texto largo sin ningún verbo declarativo
        $noVerbsText = str_repeat('La oscuridad cubre la colina silenciosa y solitaria. ', 200);

        $start = microtime(true);
        $result = $service->callTool('screenplay_formatter', [
            'structured_text' => $noVerbsText,
        ]);
        $duration = microtime(true) - $start;

        $this->assertTrue($result['success']);
        $this->assertLessThan(0.1, $duration, "El fast-path tardó {$duration}s.");
        $this->assertStringContainsString('oscuridad', $result['output']);
    }

    public function test_format_screenplay_fallback_properly_detects_indirect_dialogue(): void
    {
        Config::set('services.jg_agente.mock', true);

        $service = app(AgentClientService::class);

        $text = "JUAN mira a María fijamente y dice que no piensa marcharse jamás.\n\nMaría asiente en silencio.";

        $result = $service->callTool('screenplay_formatter', [
            'structured_text' => $text,
        ]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('JUAN', $result['output']);
        $this->assertStringContainsStringIgnoringCase('no piensa marcharse jamás', $result['output']);
    }
}
