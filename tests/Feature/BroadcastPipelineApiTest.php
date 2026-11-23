<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Broadcast\AutoGenerateBroadcastShotsAction;
use App\DTOs\Broadcast\BroadcastJobRequestDTO;
use App\Enums\BlockStage;
use App\Enums\BroadcastJobStage;
use App\Jobs\RunBroadcastStageJob;
use App\Models\BroadcastJob;
use App\Services\JgAgente\AgentClientService;
use App\Services\JgAgente\HmacSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class BroadcastPipelineApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.jg_agente.secret', 'test-broadcast-secret-1234567890');
    }

    /**
     * Test 1: Rechaza peticiones sin cabeceras HMAC o con firma inválida (401).
     */
    public function test_rejects_requests_without_hmac_headers_or_invalid_signature(): void
    {
        $payload = [
            'channel_id' => 'ch-unauthorized',
            'format' => 'news',
            'title' => 'Noticia de Prueba',
            'raw_text' => 'Texto de prueba con más de diez caracteres requeridos.',
        ];

        // 1. POST sin cabeceras HMAC -> 401
        $responseNoHeaders = $this->postJson('/api/v1/broadcast/jobs', $payload);
        $responseNoHeaders->assertStatus(401);
        $responseNoHeaders->assertJson(['error' => 'Cabeceras de autenticación HMAC inválidas o ausentes']);

        // 2. POST con firma inválida -> 401
        $invalidSignatureHeaders = [
            'X-Signature' => 'invalid-hmac-signature-abc123456789',
            'X-Timestamp' => (string) time(),
            'X-Nonce' => (string) Str::uuid(),
        ];

        $responseInvalidSig = $this->postJson('/api/v1/broadcast/jobs', $payload, $invalidSignatureHeaders);
        $responseInvalidSig->assertStatus(401);
        $responseInvalidSig->assertJson(['error' => 'Firma HMAC inválida']);
    }

    /**
     * Test 2: Rechaza timestamp expirado y reutilización de nonce (anti-replay) (401).
     */
    public function test_rejects_expired_timestamp_and_replayed_nonce(): void
    {
        $payload = [
            'channel_id' => 'ch-security-test',
            'format' => 'pill',
            'title' => 'Prueba de Seguridad Anti-Replay',
            'raw_text' => 'Texto bruto válido para verificación de ventana temporal y reintentos.',
        ];

        // 1. Timestamp de hace 400 segundos (> MAX_SKEW_SECONDS de 300) -> 401
        $expiredTimestamp = time() - 400;
        $expiredHeaders = $this->generateHmacHeaders('POST', '/api/v1/broadcast/jobs', $payload, timestamp: $expiredTimestamp);

        $responseExpired = $this->postJson('/api/v1/broadcast/jobs', $payload, $expiredHeaders);
        $responseExpired->assertStatus(401);
        $responseExpired->assertJson(['error' => 'Timestamp fuera de ventana']);

        // 2. Mismo nonce reutilizado en una segunda petición -> 401
        $reusedNonce = (string) Str::uuid();
        $validHeadersFirst = $this->generateHmacHeaders('POST', '/api/v1/broadcast/jobs', $payload, timestamp: time(), nonce: $reusedNonce);

        $firstResponse = $this->postJson('/api/v1/broadcast/jobs', $payload, $validHeadersFirst);
        $firstResponse->assertStatus(202);

        // Segunda petición con el mismo nonce
        $secondHeaders = $this->generateHmacHeaders('POST', '/api/v1/broadcast/jobs', $payload, timestamp: time(), nonce: $reusedNonce);
        $secondResponse = $this->postJson('/api/v1/broadcast/jobs', $payload, $secondHeaders);
        $secondResponse->assertStatus(401);
        $secondResponse->assertJson(['error' => 'Nonce ya utilizado']);
    }

    /**
     * Test 3: Valida payload de entrada y encola RunBroadcastStageJob en cola 'broadcast' en estado RAW (202).
     */
    public function test_store_validates_payload_and_dispatches_stage_job_on_broadcast_queue(): void
    {
        Queue::fake();

        // 1. Payload con format inválido -> 422
        $invalidPayload = [
            'channel_id' => 'ch-invalid-format',
            'format' => 'invalid',
            'title' => 'Título de prueba',
            'raw_text' => 'Texto de longitud superior al umbral mínimo exigido.',
        ];
        $invalidHeaders = $this->generateHmacHeaders('POST', '/api/v1/broadcast/jobs', $invalidPayload);

        $responseInvalid = $this->postJson('/api/v1/broadcast/jobs', $invalidPayload, $invalidHeaders);
        $responseInvalid->assertStatus(422);
        $responseInvalid->assertJsonValidationErrors(['format']);

        Queue::assertNothingPushed();

        // 2. Payload válido firmado con HmacSigner -> 202
        $validPayload = [
            'channel_id' => 'ch-tech-breakdown',
            'format' => 'news',
            'title' => 'Revolución en Computación Cuántica',
            'raw_text' => 'Un grupo de científicos ha demostrado la supremacía cuántica práctica en la optimización de redes neuronales.',
            'language' => 'es',
            'sources' => ['https://nature.example.com/quantum-neural'],
            'visual_mode' => 'cinematic_realistic',
        ];
        $validHeaders = $this->generateHmacHeaders('POST', '/api/v1/broadcast/jobs', $validPayload);

        $responseValid = $this->postJson('/api/v1/broadcast/jobs', $validPayload, $validHeaders);
        $responseValid->assertStatus(202);
        $responseValid->assertJsonStructure([
            'job_id',
            'channel_id',
            'format',
            'stage',
            'status',
            'project_id',
            'script_block_id',
        ]);

        $jobId = (string) $responseValid->json('job_id');
        $job = BroadcastJob::findOrFail($jobId);

        $this->assertSame(BroadcastJobStage::RAW, $job->stage);
        $this->assertNotNull($job->project);
        $this->assertNotNull($job->scriptBlock);
        $this->assertSame(BlockStage::RAW->value, $job->scriptBlock->stage);
        $this->assertNotNull($job->scriptBlock->scene);
        $this->assertSame($job->project_id, $job->scriptBlock->scene->project_id);

        Queue::assertPushed(RunBroadcastStageJob::class, function (RunBroadcastStageJob $pushedJob) use ($jobId): bool {
            return $pushedJob->broadcastJobId === $jobId
                && $pushedJob->queue === 'broadcast'
                && $pushedJob->targetStage === BroadcastJobStage::TREATMENT;
        });
    }

    /**
     * Test 4: Pipeline headless completo RAW -> TREATMENT -> SCREENPLAY -> SHOTS con limpieza oral sin Markdown.
     */
    public function test_headless_pipeline_executes_raw_to_shots_with_clean_oral_text_and_returns_artifacts(): void
    {
        $longMarkdownDescription = '# Titular Exclusivo con **Negrita Relevante** y [enlace al informe](https://example.com/report).'
            .' En los laboratorios de computación más avanzados de Europa, un consorcio científico internacional'
            .' ha completado exitosamente la primera prueba de transmisión de datos cuánticos en tiempo real con coherencia'
            .' cuántica estable durante más de quince minutos continuados sin degradación alguna del canal.'
            .' Este logro sin precedentes abre el camino a una era de comunicaciones seguras y aprendizaje automático distribuido'
            .' capaz de resolver problemas complejos de física molecular y modelado climatológico a escala planetaria.';

        $secondMarkdownDescription = 'PRESENTADOR: **Atención al avance**: # Segunda revelación con otro [enlace oficial](https://example.com/press).'
            .' Los técnicos e ingenieros han certificado que toda la infraestructura de telecomunicaciones'
            .' existente puede adaptarse mediante módulos ópticos estándar sin necesidad de desplegar nuevas líneas'
            .' terrestres o submarinas durante la próxima década de modernización tecnológica global.';

        $mockAgent = Mockery::mock(AgentClientService::class);
        $this->app->instance(AgentClientService::class, $mockAgent);

        $mockAgent->shouldReceive('getLastTelemetry')
            ->andReturn(['model' => 'gpt-test', 'total_tokens' => 180]);

        $mockAgent->shouldReceive('structureTreatment')
            ->once()
            ->andReturn("NARRADOR\nUn equipo científico logra un hito histórico en transmisión cuántica estable.");

        $mockAgent->shouldReceive('formatScreenplay')
            ->once()
            ->andReturn("PRESENTADOR\nBuenas tardes. Les informamos del mayor avance en física cuántica del siglo XXI.");

        $mockAgent->shouldReceive('breakdownShots')
            ->once()
            ->andReturn([
                'annotated_text' => '--- shot 1 (PM) --- ... --- shot 2 (PP) --- ...',
                'shots' => [
                    [
                        'shot_number' => 1,
                        'shot_type' => 'PM',
                        'description' => $longMarkdownDescription,
                    ],
                    [
                        'shot_number' => 2,
                        'shot_type' => 'PP',
                        'description' => $secondMarkdownDescription,
                    ],
                ],
            ]);

        $payload = [
            'channel_id' => 'ch-broadcast-headless',
            'format' => 'news',
            'title' => 'Avance Histórico Cuántico',
            'raw_text' => 'Texto bruto completo para procesar la pauta editorial headless hasta la etapa de planos.',
            'language' => 'es',
        ];

        $postHeaders = $this->generateHmacHeaders('POST', '/api/v1/broadcast/jobs', $payload);
        $postResponse = $this->postJson('/api/v1/broadcast/jobs', $payload, $postHeaders);
        $postResponse->assertStatus(202);

        $jobId = (string) $postResponse->json('job_id');

        // Consultar el estado y artefactos vía GET firmado con HMAC
        $getPath = '/api/v1/broadcast/jobs/'.$jobId;
        $getHeaders = $this->generateHmacHeaders('GET', $getPath);
        $getResponse = $this->get($getPath, $getHeaders);

        $getResponse->assertStatus(200);
        $getResponse->assertJson([
            'job_id' => $jobId,
            'stage' => BroadcastJobStage::SHOTS->value,
            'status' => BroadcastJobStage::SHOTS->value,
        ]);

        $artifacts = $getResponse->json('artifacts');
        $this->assertIsArray($artifacts);
        $this->assertArrayHasKey('treatment', $artifacts);
        $this->assertArrayHasKey('screenplay', $artifacts);
        $this->assertArrayHasKey('shots', $artifacts);
        $this->assertNotEmpty($artifacts['shots']);

        /** @var list<array{shot_id: string, shot_number: int, shot_type: string|null, oral_text: string, word_count: int, estimated_seconds: int}> $shots */
        $shots = $artifacts['shots'];

        foreach ($shots as $shot) {
            $oralText = $shot['oral_text'];
            $wordCount = $shot['word_count'];
            $estimatedSeconds = $shot['estimated_seconds'];

            $this->assertStringNotContainsString('**', $oralText, 'El texto oral no debe contener negritas Markdown');
            $this->assertStringNotContainsString('#', $oralText, 'El texto oral no debe contener encabezados Markdown');
            $this->assertStringNotContainsString('[', $oralText, 'El texto oral no debe contener corchetes de enlaces Markdown');
            $this->assertStringNotContainsString(']', $oralText, 'El texto oral no debe contener corchetes de enlaces Markdown');

            $this->assertLessThanOrEqual(60, $wordCount, 'El conteo de palabras debe ser <= 60');
            $this->assertGreaterThanOrEqual(12, $estimatedSeconds, 'La duración estimada debe ser >= 12s');
            $this->assertLessThanOrEqual(25, $estimatedSeconds, 'La duración estimada debe ser <= 25s');
        }
    }

    /**
     * Test 5: Resiliencia de etapas: preserva artefactos en fallo, marca FAILED e idempotencia de executeStage.
     */
    public function test_stage_resilience_preserves_completed_stages_on_retry_and_marks_failed(): void
    {
        Queue::fake();

        $mockAgent = Mockery::mock(AgentClientService::class);
        $this->app->instance(AgentClientService::class, $mockAgent);

        $mockAgent->shouldReceive('getLastTelemetry')
            ->andReturn(['model' => 'test-llm']);

        // 1. Ejecutar TREATMENT correctamente (se llamará una sola vez)
        $expectedTreatmentText = "NARRADOR\nTratamiento estructurado exitoso que debe preservarse.";
        $mockAgent->shouldReceive('structureTreatment')
            ->once()
            ->andReturn($expectedTreatmentText);

        $action = app(AutoGenerateBroadcastShotsAction::class);
        $dto = BroadcastJobRequestDTO::fromArray([
            'channel_id' => 'ch-resilience',
            'format' => 'pill',
            'title' => 'Resilience Test',
            'raw_text' => 'Texto bruto inicial con suficientes caracteres para el test de resiliencia.',
        ]);

        $job = $action->initialize($dto);
        $this->assertSame(BroadcastJobStage::RAW, $job->stage);

        $job = $action->executeStage($job, BroadcastJobStage::TREATMENT);
        $this->assertSame(BroadcastJobStage::TREATMENT, $job->stage);
        $this->assertArrayHasKey('treatment', $job->artifacts);
        $this->assertSame($expectedTreatmentText, $job->artifacts['treatment']);
        $this->assertSame($expectedTreatmentText, $job->scriptBlock->fresh()->structured_text);

        // 2. Simular fallo en SCREENPLAY e invocar RunBroadcastStageJob::failed(...)
        $screenplayJob = new RunBroadcastStageJob($job->id, BroadcastJobStage::SCREENPLAY);
        $simulatedException = new \RuntimeException('Error simulado en LLM al generar SCREENPLAY por timeout');
        $screenplayJob->failed($simulatedException);

        $job->refresh();
        $this->assertSame(BroadcastJobStage::FAILED, $job->stage);
        $this->assertSame(BroadcastJobStage::SCREENPLAY->value, $job->failed_stage);
        $this->assertStringContainsString('Error simulado en LLM al generar SCREENPLAY', (string) $job->error_message);

        // Verificar que los artefactos previos y el texto estructurado se conservan intactos
        $this->assertArrayHasKey('treatment', $job->artifacts);
        $this->assertSame($expectedTreatmentText, $job->artifacts['treatment']);
        $this->assertSame($expectedTreatmentText, $job->scriptBlock->fresh()->structured_text);

        // 3. Verificar que executeStage(..., TREATMENT) es idempotente y no vuelve a llamar al LLM si ya se superó TREATMENT
        $job->update([
            'stage' => BroadcastJobStage::TREATMENT,
            'failed_stage' => null,
            'error_message' => null,
        ]);

        $idempotentJob = $action->executeStage($job, BroadcastJobStage::TREATMENT);
        $this->assertSame(BroadcastJobStage::TREATMENT, $idempotentJob->stage);

        $job->update([
            'stage' => BroadcastJobStage::SCREENPLAY,
            'failed_stage' => null,
            'error_message' => null,
        ]);

        $idempotentJobPast = $action->executeStage($job, BroadcastJobStage::TREATMENT);
        $this->assertSame(BroadcastJobStage::SCREENPLAY, $idempotentJobPast->stage);
    }

    /**
     * Helper para generar cabeceras HMAC canónicas válidas.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array<string, string>
     */
    private function generateHmacHeaders(
        string $method,
        string $path,
        ?array $payload = null,
        ?int $timestamp = null,
        ?string $nonce = null,
        ?string $overrideSecret = null
    ): array {
        $rawBody = $payload !== null ? (string) json_encode($payload) : '';
        $secret = $overrideSecret ?? (string) config('services.jg_agente.secret');
        $signer = new HmacSigner;

        return $signer->generateSignatureHeaders(
            method: $method,
            path: $path,
            rawBody: $rawBody,
            secret: $secret,
            timestamp: $timestamp,
            nonce: $nonce
        );
    }
}
