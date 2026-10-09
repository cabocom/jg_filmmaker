<?php

namespace Tests\Feature;

use App\Services\JgAgente\AgentClientService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentClientHmacSignatureTest extends TestCase
{
    public function test_signature_headers_comply_with_canonical_hmac_sha256_specification(): void
    {
        config([
            'services.jg_agente.secret' => 'test_secret_key_abcdef123456',
            'services.jg_agente.url' => 'http://127.0.0.1:8001',
            'services.jg_agente.mock' => false,
        ]);

        $client = new AgentClientService;

        $method = 'POST';
        $path = '/api/v1/tools/treatment_structuring';
        $rawBody = json_encode(['raw_text' => 'El viento sopla en la costa'], JSON_UNESCAPED_SLASHES);
        $timestamp = 1773090000;

        $headers = $client->generateSignatureHeaders($method, $path, $rawBody, $timestamp);

        // 1. Cabeceras obligatorias presentes
        $this->assertArrayHasKey('X-Signature', $headers);
        $this->assertArrayHasKey('X-Timestamp', $headers);
        $this->assertArrayHasKey('X-Correlation-ID', $headers);
        $this->assertSame('application/json', $headers['Content-Type']);

        // 2. Timestamp coincide
        $this->assertSame((string) $timestamp, $headers['X-Timestamp']);

        // 3. Correlation ID es un UUID válido
        $this->assertTrue(Str::isUuid($headers['X-Correlation-ID']));

        // 4. Verificación criptográfica exacta de la firma
        $bodyHash = hash('sha256', $rawBody);
        $canonicalString = "POST\n/api/v1/tools/treatment_structuring\n1773090000\n{$bodyHash}";
        $expectedSignature = hash_hmac('sha256', $canonicalString, 'test_secret_key_abcdef123456');

        $this->assertSame($expectedSignature, $headers['X-Signature']);
        $this->assertSame(64, strlen($headers['X-Signature'])); // SHA256 hex string
    }

    public function test_signature_changes_when_body_is_altered_by_one_byte(): void
    {
        config(['services.jg_agente.secret' => 'secret_123']);
        $client = new AgentClientService;

        $bodyA = '{"text": "A"}';
        $bodyB = '{"text": "B"}';
        $timestamp = 1773090000;

        $headersA = $client->generateSignatureHeaders('POST', '/api/v1/tools/foo', $bodyA, $timestamp);
        $headersB = $client->generateSignatureHeaders('POST', '/api/v1/tools/foo', $bodyB, $timestamp);

        $this->assertNotSame($headersA['X-Signature'], $headersB['X-Signature']);
    }

    public function test_http_call_sends_verified_hmac_headers_to_agent(): void
    {
        config([
            'services.jg_agente.secret' => 'auth_shared_secret',
            'services.jg_agente.url' => 'http://jg_agente_service:8001',
            'services.jg_agente.mock' => false,
        ]);

        Http::fake([
            'http://jg_agente_service:8001/api/v1/tools/*' => Http::response([
                'success' => true,
                'output' => 'INT. BAR CENTRAL - NOCHE',
                'metadata' => ['stage' => 'screenplay'],
            ], 200),
        ]);

        $client = new AgentClientService;
        $result = $client->formatScreenplay('Texto estructurado', []);

        $this->assertSame('INT. BAR CENTRAL - NOCHE', $result);

        Http::assertSent(function ($request) {
            $hasSig = $request->hasHeader('X-Signature');
            $hasTimestamp = $request->hasHeader('X-Timestamp');
            $hasCorr = $request->hasHeader('X-Correlation-ID');

            if (! $hasSig || ! $hasTimestamp || ! $hasCorr) {
                return false;
            }

            // Validar que la cabecera enviada es matemáticamente correcta contra el body
            $sentBody = $request->body();
            $bodySha = hash('sha256', $sentBody);
            $timestamp = $request->header('X-Timestamp')[0];
            $path = parse_url($request->url(), PHP_URL_PATH);
            $canonical = "POST\n{$path}\n{$timestamp}\n{$bodySha}";
            $expected = hash_hmac('sha256', $canonical, 'auth_shared_secret');

            return $request->header('X-Signature')[0] === $expected;
        });
    }
}
