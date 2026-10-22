<?php

namespace Tests\Feature;

use App\Services\JgAgente\AgentClientService;
use Tests\TestCase;

class ScreenplayAttributionTest extends TestCase
{
    /**
     * Valida que no exista atribución espuria al primer personaje de la Biblia (p. ej. CAMARERO)
     * cuando las acciones o conversaciones son entre otros personajes.
     */
    public function test_format_screenplay_does_not_falsely_attribute_dialogue_to_first_character(): void
    {
        $agent = app(AgentClientService::class);

        // Simulamos la Biblia de producción donde CAMARERO es el primer personaje registrado
        $characters = [
            ['name' => 'CAMARERO'],
            ['name' => 'GERMÁN'],
            ['name' => 'JUAN'],
            ['name' => 'LEIDI MACARENA'],
            ['name' => 'ESTRELLA CASILDA'],
        ];

        $rawText = <<<'TEXT'
INT. CALLEJÓN OSCURO - NOCHE

GERMÁN está huyendo por una calle vacía mirando hacia atrás. El viento sopla fuertemente.

GERMÁN se gira mientras corre y choca contra JUAN que aparece de repente. GERMÁN cae al suelo.

LEIDI MACARENA le pregunta a Germán qué está haciendo aquí tan tarde.

ESTRELLA CASILDA le responde que se calle la boca y que no se meta en sus asuntos.

GERMÁN intenta levantarse mientras mira a JUAN diciéndole que le deje marchar.
TEXT;

        $screenplay = $agent->formatScreenplay($rawText, $characters);

        // 1. CAMARERO no debe aparecer jamás en el guion resultante
        $this->assertStringNotContainsString('CAMARERO', $screenplay, 'CAMARERO no debe ser atribuido cuando no participa en la escena.');

        // 2. LEIDI MACARENA debe ser la hablante de su réplica
        $this->assertStringContainsString("LEIDI MACARENA\n", $screenplay);
        $this->assertStringContainsString('¿Qué estás haciendo aquí tan tarde?', $screenplay);

        // 3. ESTRELLA CASILDA debe ser la hablante de su respuesta
        $this->assertStringContainsString("ESTRELLA CASILDA\n", $screenplay);
        $this->assertStringContainsString('Te calles la boca', $screenplay);

        // 4. GERMÁN debe ser el hablante de su réplica a Juan
        $this->assertStringContainsString("GERMÁN\n", $screenplay);
        $this->assertStringContainsString('Me dejes marchar', $screenplay);
    }

    /**
     * Valida la segmentación de párrafos con diálogos múltiples unidos por conjunciones.
     */
    public function test_format_screenplay_splits_dialogue_chained_by_conjunctions(): void
    {
        $agent = app(AgentClientService::class);

        $characters = [
            ['name' => 'CAMARERO'],
            ['name' => 'LEIDI MACARENA'],
            ['name' => 'ESTRELLA CASILDA'],
        ];

        $rawText = 'LEIDI MACARENA le pregunta a Estrella Casilda si tiene la llave y Estrella Casilda le contesta que jamás se la dará.';

        $screenplay = $agent->formatScreenplay($rawText, $characters);

        $this->assertStringNotContainsString('CAMARERO', $screenplay);
        $this->assertStringContainsString("LEIDI MACARENA\n", $screenplay);
        $this->assertStringContainsString("ESTRELLA CASILDA\n", $screenplay);
        $this->assertStringContainsString('dará', $screenplay);
    }
}
