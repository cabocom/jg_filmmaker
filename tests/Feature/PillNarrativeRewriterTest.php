<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Tv\PillNarrativeRewriter;
use App\Services\Tv\WikipediaRundownSynthesizer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PillNarrativeRewriterTest extends TestCase
{
    public function test_rewriter_uses_llm_with_legacy_focused_prompt_when_configured(): void
    {
        config()->set('services.llm.api_key', 'test-llm-key');
        config()->set('services.llm.provider', 'groq');
        config()->set('services.llm.model', 'test-model');

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => 'Alan Turing sentó las bases teóricas de la computación moderna y descifró Enigma.',
                    ],
                ]],
            ], 200),
        ]);

        $rewriter = app(PillNarrativeRewriter::class);
        $output = $rewriter->rewrite(
            'Alan Turing',
            'Alan Mathison Turing (Londres, 1912-1954), hijo de Julius Turing, formalizó los algoritmos y la máquina de Turing.',
            'es',
            125
        );

        $this->assertSame('Alan Turing sentó las bases teóricas de la computación moderna y descifró Enigma.', $output);

        Http::assertSent(function ($request): bool {
            $body = $request->data();
            $system = (string) ($body['messages'][0]['content'] ?? '');

            return str_contains($system, 'huella o impacto')
                && str_contains($system, 'PROHIBIDO incluir datos biográficos triviales');
        });
    }

    public function test_rewriter_deterministic_fallback_strips_birth_death_and_parents_noise(): void
    {
        config()->set('services.llm.api_key', '');

        $raw = 'Ada Lovelace (Londres, 10 de diciembre de 1815 - 27 de noviembre de 1852) fue una matemática británica. '
            .'Nació el 10 de diciembre de 1815 en Londres. '
            .'Era hija de Lord Byron y Annabella Milbanke. '
            .'Escribió el primer algoritmo destinado a ser procesado por una máquina, anticipando el potencial de los ordenadores modernos.';

        $rewriter = app(PillNarrativeRewriter::class);
        $clean = $rewriter->rewrite('Ada Lovelace', $raw, 'es', 120);

        $this->assertStringNotContainsString('1815', $clean);
        $this->assertStringNotContainsString('Lord Byron', $clean);
        $this->assertStringNotContainsString('Nació el', $clean);
        $this->assertStringContainsString('primer algoritmo', $clean);
    }

    public function test_wikipedia_synthesizer_rewrites_when_target_duration_is_given(): void
    {
        config()->set('services.llm.api_key', '');

        Http::fake([
            'https://es.wikipedia.org/api/rest_v1/page/summary/*' => Http::response([
                'title' => 'Grace Hopper',
                'extract' => 'Grace Murray Hopper (Nueva York, 1906-1992) fue una científica. Nació el 9 de diciembre de 1906. Creó el primer compilador y sentó las bases de COBOL.',
                'content_urls' => ['desktop' => ['page' => 'https://es.wikipedia.org/wiki/Grace_Hopper']],
            ], 200),
            'https://es.wikipedia.org/w/api.php*' => Http::response([
                'query' => ['pages' => [['extract' => '']]],
            ], 200),
        ]);

        $result = app(WikipediaRundownSynthesizer::class)->synthesize('Grace Hopper', 'es', 50);

        $this->assertStringNotContainsString('1906-1992', $result['script_text']);
        $this->assertStringNotContainsString('Nació el', $result['script_text']);
        $this->assertStringContainsString('primer compilador', $result['script_text']);
    }
}
