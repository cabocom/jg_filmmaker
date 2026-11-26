<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Tv\ManageTvRundownAction;
use App\Enums\BlockStage;
use App\Enums\ProjectCategory;
use App\Jobs\SynthesizeTvRundownJob;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\TvRundown;
use App\Models\TvVideo;
use App\Models\User;
use App\Services\JgAgente\HmacSigner;
use App\Services\Tv\WikipediaRundownSynthesizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class NativeTvBackendApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.jg_agente.secret', 'test-tv-rundowns-api-secret-1234567890');
    }

    public function test_wikipedia_synthesizer_separates_visual_credit_from_spoken_script_and_blocks_ssrf(): void
    {
        Http::fake([
            'https://es.wikipedia.org/api/rest_v1/page/summary/*' => Http::response([
                'title' => 'Inteligencia artificial',
                'extract' => "La inteligencia artificial\n\nes la disciplina   científica que desarrolla sistemas capaces de aprender.",
                'content_urls' => [
                    'desktop' => [
                        'page' => 'https://es.wikipedia.org/wiki/Inteligencia_artificial',
                    ],
                ],
            ], 200),
        ]);

        /** @var WikipediaRundownSynthesizer $synthesizer */
        $synthesizer = app(WikipediaRundownSynthesizer::class);

        $result = $synthesizer->synthesize('https://es.wikipedia.org/wiki/Inteligencia_artificial', 'es');

        $this->assertSame('Inteligencia artificial', $result['title']);
        $this->assertSame(
            'La inteligencia artificial es la disciplina científica que desarrolla sistemas capaces de aprender.',
            $result['script_text']
        );
        $this->assertStringNotContainsString('CC BY-SA', $result['script_text']);
        $this->assertSame(WikipediaRundownSynthesizer::VISUAL_CREDIT, $result['visual_credit']);
        $this->assertSame(['https://es.wikipedia.org/wiki/Inteligencia_artificial'], $result['sources']);

        $blockedUrls = [
            'http://es.wikipedia.org/wiki/Inteligencia_artificial',
            'https://169.254.169.254/latest/meta-data',
            'https://evil.example.com/wiki/Inteligencia_artificial',
            'https://es.wikipedia.org/api/rest_v1/page/summary/Test',
        ];

        foreach ($blockedUrls as $blockedUrl) {
            try {
                $synthesizer->synthesize($blockedUrl);
                $this->fail("Expected InvalidArgumentException for blocked URL: {$blockedUrl}");
            } catch (InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }

        Http::assertSentCount(1);
    }

    public function test_manage_tv_rundown_links_1_to_1_ai_video_project_and_dispatches_broadcast_production(): void
    {
        Queue::fake();

        $owner = User::factory()->create();

        /** @var ManageTvRundownAction $action */
        $action = app(ManageTvRundownAction::class);

        $rundown = $action->createFromPromptOrSource([
            'title' => 'Cápsula Ciencia Hoy',
            'source_type' => 'prompt',
            'format' => 'pill',
            'language' => 'es',
            'script_text' => 'Los telescopios espaciales revelan nuevas galaxias primitivas.',
            'visual_mode' => 'storyboard',
            'resolution_profile' => '1080p30',
        ], $owner);

        $rundown->load('project.scenes.scriptBlocks');

        $this->assertNotNull($rundown->project);
        $this->assertSame(ProjectCategory::AI_VIDEO, $rundown->project->category);
        $this->assertSame('Cápsula Ciencia Hoy', $rundown->project->title);
        $this->assertSame('draft', $rundown->status);

        $scene = $rundown->project->scenes->first();
        $this->assertNotNull($scene);
        $block = $scene->scriptBlocks->first();
        $this->assertNotNull($block);
        $this->assertSame(BlockStage::RAW->value, $block->stage);
        $this->assertSame('Los telescopios espaciales revelan nuevas galaxias primitivas.', $block->raw_text);

        $updatedRundown = $action->updateDraft($rundown, [
            'title' => 'Cápsula Ciencia Actualizada',
            'script_text' => 'Guion actualizado para emisión nocturna.',
        ]);

        /** @var Project $linkedProject */
        $linkedProject = $updatedRundown->project()->with('scenes.scriptBlocks')->firstOrFail();
        $this->assertSame('Cápsula Ciencia Actualizada', $linkedProject->title);
        $this->assertSame(
            'Guion actualizado para emisión nocturna.',
            $linkedProject->scenes->first()?->scriptBlocks->first()?->raw_text
        );

        $dispatched = $action->dispatchBroadcastProduction($updatedRundown);

        $this->assertSame('queued', $dispatched->status);
        $this->assertNotNull($dispatched->broadcast_job_id);

        /** @var BroadcastJob $broadcastJob */
        $broadcastJob = BroadcastJob::query()->findOrFail($dispatched->broadcast_job_id);
        $this->assertSame('Guion actualizado para emisión nocturna.', $broadcastJob->raw_text);

        /** @var TvVideo|null $tvVideo */
        $tvVideo = TvVideo::query()->where('rundown_id', $dispatched->id)->first();
        $this->assertNotNull($tvVideo);
        $this->assertSame('queued', $tvVideo->status);
        $this->assertSame($broadcastJob->id, $tvVideo->broadcast_job_id);

        $wikiRundown = $action->createFromPromptOrSource([
            'topic_or_url' => 'https://es.wikipedia.org/wiki/Telescopio_espacial_James_Webb',
            'source_type' => 'wikipedia',
            'format' => 'news',
            'language' => 'es',
        ], $owner);

        $this->assertSame('synthesizing', $wikiRundown->status);
        Queue::assertPushedOn('broadcast', SynthesizeTvRundownJob::class, function (SynthesizeTvRundownJob $job) use ($wikiRundown): bool {
            return $job->rundownId === $wikiRundown->id;
        });
    }

    public function test_m2m_tv_rundowns_api_creates_lists_and_shows_rundowns_with_hmac(): void
    {
        Queue::fake();

        $payload = [
            'title' => 'Boletín Tecnológico M2M',
            'source_type' => 'prompt',
            'format' => 'pill',
            'language' => 'es',
            'script_text' => 'La energía de fusión alcanza un nuevo hito experimental.',
            'visual_credit' => 'Fuente: Archivo Científico',
            'resolution_profile' => '1080p30',
            'dispatch_production' => true,
        ];

        // 1. Unsigned or invalid HMAC -> 401
        $this->getJson('/api/v1/tv/rundowns')
            ->assertStatus(401)
            ->assertJson(['error' => 'Cabeceras de autenticación HMAC inválidas o ausentes']);

        $this->postJson('/api/v1/tv/rundowns', $payload)
            ->assertStatus(401)
            ->assertJson(['error' => 'Cabeceras de autenticación HMAC inválidas o ausentes']);

        $this->postJson('/api/v1/tv/rundowns', $payload, [
            'X-Signature' => 'invalid-signature-0000',
            'X-Timestamp' => (string) time(),
            'X-Nonce' => (string) Str::uuid(),
        ])
            ->assertStatus(401)
            ->assertJson(['error' => 'Firma HMAC inválida']);

        // 2. Create rundown with valid HMAC -> 201
        $createHeaders = $this->generateHmacHeaders('POST', '/api/v1/tv/rundowns', $payload);
        $createResponse = $this->postJson('/api/v1/tv/rundowns', $payload, $createHeaders);

        $createResponse->assertStatus(201);
        $createResponse->assertJsonPath('title', 'Boletín Tecnológico M2M');
        $createResponse->assertJsonPath('status', 'queued');
        $createResponse->assertJsonPath('tv_video.status', 'queued');

        $rundownId = (string) $createResponse->json('id');
        $this->assertNotSame('', $rundownId);
        $this->assertNotNull(TvRundown::query()->find($rundownId));

        // 3. List rundowns with valid HMAC -> 200
        $listHeaders = $this->generateHmacHeaders('GET', '/api/v1/tv/rundowns');
        $listResponse = $this->getJson('/api/v1/tv/rundowns?status=queued&format=pill', $listHeaders);

        $listResponse->assertStatus(200);
        $listResponse->assertJsonPath('meta.total', 1);
        $listResponse->assertJsonPath('data.0.id', $rundownId);
        $listResponse->assertJsonPath('data.0.title', 'Boletín Tecnológico M2M');

        // 4. Show existing rundown -> 200
        $showHeaders = $this->generateHmacHeaders('GET', "/api/v1/tv/rundowns/{$rundownId}");
        $showResponse = $this->getJson("/api/v1/tv/rundowns/{$rundownId}", $showHeaders);

        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('id', $rundownId);
        $showResponse->assertJsonPath('visual_credit', 'Fuente: Archivo Científico');

        // 5. Show missing rundown -> 404
        $missingId = (string) Str::uuid();
        $missingHeaders = $this->generateHmacHeaders('GET', "/api/v1/tv/rundowns/{$missingId}");
        $missingResponse = $this->getJson("/api/v1/tv/rundowns/{$missingId}", $missingHeaders);

        $missingResponse->assertStatus(404);
        $missingResponse->assertJson(['error' => 'TV rundown not found']);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, string>
     */
    private function generateHmacHeaders(
        string $method,
        string $path,
        ?array $payload = null,
        ?int $timestamp = null,
        ?string $nonce = null
    ): array {
        $rawBody = $payload !== null ? (string) json_encode($payload) : '';
        $secret = (string) config('services.jg_agente.secret');
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
