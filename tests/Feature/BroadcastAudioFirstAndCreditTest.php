<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Broadcast\SynthesizeBroadcastMasterAudioAction;
use App\Enums\BroadcastJobStage;
use App\Enums\ProjectCategory;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\User;
use App\Services\Broadcast\SentenceBasedShotDistributor;
use App\Services\Broadcast\ShotSubtitleGenerator;
use App\Services\JgAgente\HmacSigner;
use App\Services\TextToSpeechService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class BroadcastAudioFirstAndCreditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.jg_agente.secret', 'test-broadcast-secret-1234567890');
    }

    public function test_sentence_distributor_never_splits_into_word_fragments_and_strips_wikipedia_license(): void
    {
        $distributor = app(SentenceBasedShotDistributor::class);

        $shots = [
            ['shot_id' => 'shot-1', 'shot_number' => 1, 'shot_type' => 'WIDE'],
            ['shot_id' => 'shot-2', 'shot_number' => 2, 'shot_type' => 'MEDIUM'],
            ['shot_id' => 'shot-3', 'shot_number' => 3, 'shot_type' => 'CLOSE'],
        ];

        $rawText = 'La astronomía moderna ha revolucionado nuestra comprensión del universo. '
            .'Los telescopios espaciales capturan galaxias a miles de millones de años luz. '
            .'Cada descubrimiento abre nuevas preguntas sobre la materia oscura. '
            .'El futuro de la exploración espacial promete hallazgos extraordinarios. '
            .'Fuente: Wikipedia · CC BY-SA 4.0.';

        $result = $distributor->distribute($shots, $rawText);

        $this->assertSame('Fuente: Wikipedia · CC BY-SA 4.0', $result['visual_credit']);
        $this->assertStringNotContainsString('Wikipedia', $result['full_oral_text']);
        $this->assertStringNotContainsString('CC BY-SA', $result['full_oral_text']);
        $this->assertCount(3, $result['shots']);

        foreach ($result['shots'] as $shot) {
            $oral = (string) $shot['oral_text'];
            $this->assertNotSame('', $oral);
            $this->assertMatchesRegularExpression('/[.!?]$/u', $oral);
            $this->assertStringNotContainsString('Wikipedia', $oral);
            $this->assertStringNotContainsString('CC BY-SA', $oral);
        }

        // Cuando hay menos oraciones que planos, no se crean planos vacíos ni fragmentos
        $fewerSentences = $distributor->distribute(
            array_merge($shots, [['shot_id' => 'shot-4', 'shot_number' => 4, 'shot_type' => 'DETAIL']]),
            'Primera oración completa. Segunda oración completa.'
        );
        $this->assertCount(2, $fewerSentences['shots']);
        $this->assertSame('Primera oración completa.', $fewerSentences['shots'][0]['oral_text']);
        $this->assertSame('Segunda oración completa.', $fewerSentences['shots'][1]['oral_text']);
    }

    public function test_master_audio_action_synthesizes_once_with_configured_voice_and_computes_shot_windows(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'category' => ProjectCategory::AI_VIDEO->value,
        ]);

        $fakeWavPath = storage_path('app/private/test_master_audio_'.uniqid().'.wav');
        if (! is_dir(dirname($fakeWavPath))) {
            mkdir(dirname($fakeWavPath), 0755, true);
        }
        file_put_contents($fakeWavPath, base64_decode('UklGRjIAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA='));

        $mockTts = Mockery::mock(TextToSpeechService::class);
        $mockTts->shouldReceive('synthesize')
            ->once()
            ->withArgs(function (string $text, ?string $voice, float $speed, ?Project $proj) use ($project): bool {
                return $voice === 'Orus'
                    && $speed === 1.0
                    && $proj?->id === $project->id
                    && str_contains($text, 'Primera oración del episodio.')
                    && str_contains($text, 'Segunda oración mucho más extensa para comprobar el reparto proporcional.');
            })
            ->andReturn($fakeWavPath);

        $this->app->instance(TextToSpeechService::class, $mockTts);

        $job = BroadcastJob::create([
            'channel_id' => 'ch-audio-first',
            'format' => 'news',
            'title' => 'Audio First Test',
            'raw_text' => 'Primera oración del episodio. Segunda oración mucho más extensa para comprobar el reparto proporcional.',
            'language' => 'es',
            'sources' => [],
            'visual_mode' => 'storyboard',
            'stage' => BroadcastJobStage::SHOTS,
            'project_id' => $project->id,
            'artifacts' => [
                'tts_voice' => 'Orus',
                'visual_credit' => 'Fuente: Wikipedia · CC BY-SA 4.0',
                'full_oral_text' => 'Primera oración del episodio. Segunda oración mucho más extensa para comprobar el reparto proporcional.',
                'shots' => [
                    [
                        'shot_id' => 's1',
                        'shot_number' => 1,
                        'oral_text' => 'Primera oración del episodio.',
                    ],
                    [
                        'shot_id' => 's2',
                        'shot_number' => 2,
                        'oral_text' => 'Segunda oración mucho más extensa para comprobar el reparto proporcional.',
                    ],
                ],
            ],
        ]);

        try {
            $action = app(SynthesizeBroadcastMasterAudioAction::class);
            $masterAudio = $action->execute($job);

            $this->assertSame($fakeWavPath, $masterAudio['audio_path']);
            $this->assertSame('Orus', $masterAudio['voice']);
            $this->assertGreaterThan(0.0, $masterAudio['total_duration_seconds']);
            $this->assertFileExists((string) $masterAudio['ass_path']);

            $assContent = (string) file_get_contents((string) $masterAudio['ass_path']);
            $this->assertStringContainsString('Style: Credit,', $assContent);
            $this->assertStringContainsString('Fuente: Wikipedia · CC BY-SA 4.0', $assContent);

            $timedShots = $masterAudio['timed_shots'];
            $this->assertCount(2, $timedShots);
            $this->assertSame(0.0, $timedShots[0]['start_seconds']);
            $this->assertGreaterThan(0.0, $timedShots[0]['duration_seconds']);
            $this->assertEqualsWithDelta(
                $timedShots[0]['duration_seconds'],
                $timedShots[1]['start_seconds'],
                0.001
            );
            $this->assertEqualsWithDelta(
                $masterAudio['total_duration_seconds'],
                $timedShots[0]['duration_seconds'] + $timedShots[1]['duration_seconds'],
                0.01
            );

            // Segunda ejecución es idempotente y no vuelve a invocar TTS
            $idempotent = $action->execute($job->refresh());
            $this->assertSame($masterAudio['audio_path'], $idempotent['audio_path']);
        } finally {
            @unlink($fakeWavPath);
            if (isset($masterAudio['ass_path']) && is_string($masterAudio['ass_path'])) {
                @unlink($masterAudio['ass_path']);
            }
        }
    }

    public function test_broadcast_api_accepts_tts_voice_visual_credit_and_export_flags(): void
    {
        Queue::fake();

        $payload = [
            'channel_id' => 'ch-voice-flags',
            'format' => 'news',
            'title' => 'Episodio con Voz y Flags',
            'raw_text' => 'Texto completo de prueba para validar la persistencia de voz, crédito visual y banderas de exportación.',
            'tts_voice' => 'Orus',
            'tts_provider' => 'google_ai',
            'visual_credit' => 'Crédito: Archivo Histórico Nacional',
            'publish_to_podcast' => false,
            'generate_short' => false,
        ];

        $headers = (new HmacSigner)->generateSignatureHeaders(
            method: 'POST',
            path: '/api/v1/broadcast/jobs',
            rawBody: (string) json_encode($payload),
            secret: (string) config('services.jg_agente.secret'),
        );

        $response = $this->postJson('/api/v1/broadcast/jobs', $payload, $headers);
        $response->assertStatus(202);

        $job = BroadcastJob::findOrFail((string) $response->json('job_id'));
        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];

        $this->assertSame('Orus', $artifacts['tts_voice'] ?? null);
        $this->assertSame('google_ai', $artifacts['tts_provider'] ?? null);
        $this->assertSame('Crédito: Archivo Histórico Nacional', $artifacts['visual_credit'] ?? null);
        $this->assertFalse($artifacts['publish_to_podcast'] ?? true);
        $this->assertFalse($artifacts['generate_short'] ?? true);
    }

    public function test_subtitle_generator_global_ass_renders_timed_dialogues_and_credit_layer(): void
    {
        $generator = new ShotSubtitleGenerator;
        $assPath = storage_path('app/private/test_global_subtitles_'.uniqid().'.ass');

        $timedShots = [
            [
                'oral_text' => 'Primer plano narrado.',
                'start_seconds' => 0.0,
                'duration_seconds' => 4.0,
            ],
            [
                'oral_text' => 'Segundo plano narrado.',
                'start_seconds' => 4.0,
                'duration_seconds' => 6.0,
            ],
        ];

        try {
            $generator->generateGlobalAss($timedShots, $assPath, 'Fuente: Wikipedia · CC BY-SA 4.0');

            $this->assertFileExists($assPath);
            $content = (string) file_get_contents($assPath);

            $this->assertStringContainsString('[V4+ Styles]', $content);
            $this->assertStringContainsString('Style: Default,', $content);
            $this->assertStringContainsString('Style: Credit,', $content);
            $this->assertStringContainsString('Dialogue: 0,0:00:00.00,0:00:04.00,Default,,0,0,0,,Primer plano narrado.', $content);
            $this->assertStringContainsString('Dialogue: 0,0:00:04.00,0:00:10.00,Default,,0,0,0,,Segundo plano narrado.', $content);
            $this->assertStringContainsString('Dialogue: 1,0:00:00.00,0:00:10.00,Credit,,0,0,0,,{\fad(400,600)}Fuente: Wikipedia · CC BY-SA 4.0', $content);
        } finally {
            @unlink($assPath);
        }
    }
}
