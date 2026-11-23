<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyHmacSignature
{
    public const MAX_SKEW_SECONDS = 300;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.jg_agente.secret', '');
        if ($secret === '') {
            return new JsonResponse(['error' => 'HMAC secret not configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $signature = trim((string) $request->header('X-Signature', ''));
        $timestampRaw = trim((string) $request->header('X-Timestamp', ''));
        $nonce = trim((string) $request->header('X-Nonce', ''));

        if ($signature === '' || $timestampRaw === '' || $nonce === '' || ! ctype_digit($timestampRaw)) {
            return new JsonResponse(['error' => 'Cabeceras de autenticación HMAC inválidas o ausentes'], Response::HTTP_UNAUTHORIZED);
        }

        if (abs(time() - (int) $timestampRaw) > self::MAX_SKEW_SECONDS) {
            return new JsonResponse(['error' => 'Timestamp fuera de ventana'], Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->verifySignature($request, $signature, $timestampRaw, $nonce, $secret)) {
            return new JsonResponse(['error' => 'Firma HMAC inválida'], Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->validateAntiReplay($nonce)) {
            return new JsonResponse(['error' => 'Nonce ya utilizado'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }

    private function verifySignature(Request $request, string $signature, string $timestampRaw, string $nonce, string $secret): bool
    {
        $method = strtoupper(trim($request->getMethod()));
        $path = '/'.ltrim($request->path(), '/');
        $rawBody = (string) $request->getContent();
        if ($request->isMethod('GET') && $rawBody === '[]') {
            $rawBody = '';
        }
        $bodySha256 = hash('sha256', $rawBody);
        $canonical = "{$method}\n{$path}\n{$timestampRaw}\n{$bodySha256}\n{$nonce}";
        $expected = hash_hmac('sha256', $canonical, $secret);

        return hash_equals($expected, strtolower($signature));
    }

    private function validateAntiReplay(string $nonce): bool
    {
        $cacheKey = 'broadcast_hmac_nonce:'.hash('sha256', $nonce);

        return (bool) Cache::add($cacheKey, true, now()->addSeconds(self::MAX_SKEW_SECONDS));
    }
}
