<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\AgentTelemetryService;
use App\Services\JgAgente\AgentClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentClientUtf8EncodingTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_screenplay_preserves_utf8_accents_and_does_not_corrupt_multibyte_characters(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $agent = app(AgentClientService::class);

        // Texto real de producción que contenía palabras acentuadas ("dará", "Germán", "Satanás") y verbos indirectos
        $structuredText = <<<'TEXT'
GERMÁN está huyendo por una calle vacía mirando hacia atrás. El viento sopla fuertemente.

GERMÁN se gira mientras corre y choca contra JUAN que aparece de repente. GERMÁN cae al suelo.

GERMÁN, en el suelo, gira la cabeza y ve a la muñeca "LEIDI MACARENA" preguntándole si le va a dar la llave o todavía tiene ganas de seguir jugando, porque ella ya se está cansando.

GERMÁN, en el suelo, le contesta que con esas llaves podría abrir las puertas del infierno para que salieran más demonios como ella y que jamás se las dará.

GERMÁN intenta levantarse mientras mira a LEIDI MACARENA diciéndole que se vaya a lameler el ojete a Satanás, que es donde debería estar. Luego saca una llave pequeña y se la traga.

LEIDI MACARENA le dice que está cansada de gilipolleces de gilipollas y luego gira la cabeza para mirar a JUAN y le brillan los ojos.

JUAN, de pie e imponente, empieza a golpear a GERMÁN con un palo diciendo que debe obedecer a la Diosa, que la palabra de LEIDI MACARENA es la verdad.

La sangre salpica y fundido a rojo.
TEXT;

        $characters = [
            ['name' => 'GERMÁN'],
            ['name' => 'JUAN'],
            ['name' => 'LEIDI MACARENA'],
        ];

        $output = $agent->formatScreenplay($structuredText, $characters);

        // 1. Validar que el resultado sea UTF-8 válido
        $this->assertTrue(mb_check_encoding($output, 'UTF-8'), 'El texto resultante debe ser UTF-8 válido.');

        // 2. Verificar que "dará" no fue recortado a "dar\xc3"
        $this->assertStringContainsString('dará', $output, 'La palabra "dará" debe conservar su vocal con tilde.');

        // 3. Verificar que "palo" no fue fragmentado en "pa"
        $this->assertStringContainsString('con un palo', $output, 'La palabra "palo" no debe fragmentarse al analizar verbos de habla.');

        // 4. Validar que la telemetría es serializable a JSON sin excepciones
        $telemetry = $agent->getLastTelemetry();
        $this->assertNotNull($telemetry);
        $json = json_encode($telemetry);
        $this->assertNotFalse($json, 'La telemetría debe ser serializable a JSON: '.json_last_error_msg());

        // 5. Verificar que AgentTelemetryService registra el log en base de datos sin JsonEncodingException
        $telemetryService = app(AgentTelemetryService::class);
        $log = $telemetryService->record(
            projectId: $project->id,
            scriptBlockId: null,
            toolName: 'screenplay_formatter',
            inputPayload: ['structured_text' => $structuredText],
            outputPayload: $output,
            telemetry: $telemetry,
            userId: $user->id
        );

        $this->assertNotNull($log);
        $this->assertDatabaseHas('agent_request_logs', [
            'id' => $log->id,
            'project_id' => $project->id,
            'tool_name' => 'screenplay_formatter',
        ]);
    }
}
