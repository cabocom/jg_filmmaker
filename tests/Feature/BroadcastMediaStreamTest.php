<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\JgAgente\HmacSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class BroadcastMediaStreamTest extends TestCase
{
    use RefreshDatabase;

    private string $tempTvMediaRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempTvMediaRoot = sys_get_temp_dir().'/tv_media_test_'.uniqid('', true);
        File::ensureDirectoryExists($this->tempTvMediaRoot);
        config()->set('services.filmmaker.tv_media_root', $this->tempTvMediaRoot);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempTvMediaRoot)) {
            File::deleteDirectory($this->tempTvMediaRoot);
        }

        parent::tearDown();
    }

    public function test_media_stream_requires_valid_hmac_signature(): void
    {
        $this->get('/api/v1/broadcast/media/stream?path=ai-today/videos/demo.mp4')
            ->assertStatus(401);
    }

    public function test_media_stream_rejects_invalid_or_traversal_paths_with_422(): void
    {
        $this->signedGet('/api/v1/broadcast/media/stream')
            ->assertStatus(422);

        $this->signedGet('/api/v1/broadcast/media/stream?path=../secret.mp4')
            ->assertStatus(422);

        $this->signedGet('/api/v1/broadcast/media/stream?path=/etc/passwd.mp4')
            ->assertStatus(422);

        $this->signedGet('/api/v1/broadcast/media/stream?path=ai-today/videos/not-mp4.txt')
            ->assertStatus(422);
    }

    public function test_media_stream_returns_404_when_file_does_not_exist(): void
    {
        $this->signedGet('/api/v1/broadcast/media/stream?path=ai-today/videos/nonexistent.mp4')
            ->assertStatus(404);
    }

    public function test_media_stream_serves_full_mp4_with_accept_ranges_header(): void
    {
        $videoDir = $this->tempTvMediaRoot.'/ai-today/videos';
        File::ensureDirectoryExists($videoDir);

        $payload = str_repeat('0123456789abcdef', 4);
        File::put($videoDir.'/demo.mp4', $payload);

        $response = $this->signedGet('/api/v1/broadcast/media/stream?path=ai-today/videos/demo.mp4');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'video/mp4');
        $response->assertHeader('Accept-Ranges', 'bytes');
        $response->assertHeader('Content-Length', '64');
        $this->assertSame($payload, $response->streamedContent());
    }

    public function test_media_stream_serves_partial_content_for_valid_byte_ranges_and_416_for_invalid(): void
    {
        $videoDir = $this->tempTvMediaRoot.'/ai-today/videos';
        File::ensureDirectoryExists($videoDir);

        $payload = str_repeat('0123456789abcdef', 4);
        File::put($videoDir.'/demo.mp4', $payload);

        $res1 = $this->signedGet(
            '/api/v1/broadcast/media/stream?path=ai-today/videos/demo.mp4',
            ['Range' => 'bytes=0-9']
        );
        $res1->assertStatus(206);
        $res1->assertHeader('Content-Range', 'bytes 0-9/64');
        $res1->assertHeader('Content-Length', '10');
        $res1->assertHeader('Accept-Ranges', 'bytes');
        $res1->assertHeader('Content-Type', 'video/mp4');
        $this->assertSame(substr($payload, 0, 10), $res1->streamedContent());

        $res2 = $this->signedGet(
            '/api/v1/broadcast/media/stream?path=ai-today/videos/demo.mp4',
            ['Range' => 'bytes=54-']
        );
        $res2->assertStatus(206);
        $res2->assertHeader('Content-Range', 'bytes 54-63/64');
        $res2->assertHeader('Content-Length', '10');
        $this->assertSame(substr($payload, 54, 10), $res2->streamedContent());

        $res3 = $this->signedGet(
            '/api/v1/broadcast/media/stream?path=ai-today/videos/demo.mp4',
            ['Range' => 'bytes=-8']
        );
        $res3->assertStatus(206);
        $res3->assertHeader('Content-Range', 'bytes 56-63/64');
        $res3->assertHeader('Content-Length', '8');
        $this->assertSame(substr($payload, 56, 8), $res3->streamedContent());

        $res4 = $this->signedGet(
            '/api/v1/broadcast/media/stream?path=ai-today/videos/demo.mp4',
            ['Range' => 'bytes=100-200']
        );
        $res4->assertStatus(416);
        $res4->assertHeader('Content-Range', 'bytes */64');
    }

    private function signedGet(string $uri, array $headers = []): TestResponse
    {
        $secret = 'test-broadcast-secret-1234567890';
        config()->set('services.jg_agente.secret', $secret);

        $parsedPath = parse_url($uri, PHP_URL_PATH) ?: $uri;
        $canonicalPath = '/'.ltrim($parsedPath, '/');

        $signedHeaders = (new HmacSigner)->generateSignatureHeaders(
            method: 'GET',
            path: $canonicalPath,
            rawBody: '',
            secret: $secret
        );

        return $this->get($uri, array_merge($signedHeaders, $headers));
    }
}
