<?php

namespace App\Services\JgAgente;

use Illuminate\Support\Str;

class HmacSigner
{
    /**
     * Genera las cabeceras canónicas HMAC-SHA256 para una petición HTTP.
     *
     * Contrato canónico: METHOD\nPATH\nTIMESTAMP\nSHA256(BODY)\nNONCE.
     *
     * @return array<string, string>
     */
    public function generateSignatureHeaders(
        string $method,
        string $path,
        string $rawBody,
        string $secret,
        ?int $timestamp = null,
        ?string $nonce = null,
        ?string $correlationId = null
    ): array {
        $timestamp = $timestamp ?? time();
        $nonce = $nonce ?? Str::uuid()->toString();
        $correlationId = $correlationId ?? Str::uuid()->toString();
        $keyId = (string) config('services.jg_agente.key_id', 'jg_filmmaker_v1');
        $bodySha256 = hash('sha256', $rawBody);
        $canonicalString = strtoupper($method)."\n".$path."\n".$timestamp."\n".$bodySha256."\n".$nonce;
        $signature = hash_hmac('sha256', $canonicalString, $secret);

        return [
            'X-Signature' => $signature,
            'X-Timestamp' => (string) $timestamp,
            'X-Nonce' => $nonce,
            'X-Correlation-ID' => $correlationId,
            'X-Key-ID' => $keyId,
            'X-Agent-ID' => 'jg_filmmaker',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
