<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\TextToSpeechService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TextToSpeechServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TextToSpeechService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google_ai.api_key' => 'testing_google_ai_key']);
        $this->service = new TextToSpeechService;
    }

    public function test_get_available_voices_returns_valid_voices(): void
    {
        $voices = $this->service->getAvailableVoices();

        $this->assertIsArray($voices);
        $this->assertNotEmpty($voices);

        $voiceIds = array_column($voices, 'id');
        $this->assertTrue(in_array('Puck', $voiceIds, true) || in_array('sharvard#M', $voiceIds, true));
        $this->assertTrue(in_array('Aoede', $voiceIds, true) || in_array('sharvard#F', $voiceIds, true));
    }

    public function test_synthesize_creates_and_caches_audio_file(): void
    {
        Http::macro('tts_testing_real', fn () => true);

        $fakeBase64 = base64_encode('RIFF_FAKE_AUDIO_DATA');
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'audio/wav',
                                        'data' => $fakeBase64,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'http://speaches:8000/v1/audio/speech*' => Http::response('RIFF_FAKE_AUDIO_DATA', 200, [
                'Content-Type' => 'audio/wav',
            ]),
        ]);

        $audioPath = $this->service->synthesize('Hola mundo desde test', 'Puck', 1.0);

        $this->assertFileExists($audioPath);
        $this->assertEquals('RIFF_FAKE_AUDIO_DATA', File::get($audioPath));

        // Segunda llamada debe dar HIT de caché sin llamar de nuevo a HTTP
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response('SHOULD_NOT_BE_CALLED', 500),
            'http://speaches:8000/v1/audio/speech*' => Http::response('SHOULD_NOT_BE_CALLED', 500),
        ]);

        $cachedPath = $this->service->synthesize('Hola mundo desde test', 'Puck', 1.0);
        $this->assertEquals($audioPath, $cachedPath);
        $this->assertEquals('RIFF_FAKE_AUDIO_DATA', File::get($cachedPath));
    }

    public function test_parse_screenplay_block_differentiates_narrator_and_characters(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);

        $character = Character::factory()->create([
            'project_id' => $project->id,
            'name' => 'CAMARERO',
            'custom_attributes' => ['voice_id' => 'Aoede'],
        ]);

        $screenplayContent = "INT. BAR CENTRAL - NOCHE 1\n\nEl local está casi vacío bajo luces tenues.\n\nCAMARERO\n(con tono cansado)\n¿Qué va a ser, amigo?\n\nLUCÍA\nUn café solo, por favor.";

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'stage' => 'screenplay',
            'screenplay_content' => $screenplayContent,
        ]);

        $segments = $this->service->parseBlockToSegments($block, [$character]);

        $this->assertCount(3, $segments);

        // Segmento 1: Slugline / Acción del narrador
        $this->assertEquals('narrator', $segments[0]['type']);
        $this->assertEquals('NARRADOR', $segments[0]['speaker']);
        $this->assertStringContainsString('INT. BAR CENTRAL - NOCHE 1', $segments[0]['text']);

        // Segmento 2: Diálogo de CAMARERO (con su voz asignada Aoede y sin la acotación entre paréntesis)
        $this->assertEquals('dialogue', $segments[1]['type']);
        $this->assertEquals('CAMARERO', $segments[1]['speaker']);
        $this->assertEquals('Aoede', $segments[1]['voice']);
        $this->assertEquals('¿Qué va a ser, amigo?', $segments[1]['text']);

        // Segmento 3: Diálogo de LUCÍA (voz femenina deducida por nombre)
        $this->assertEquals('dialogue', $segments[2]['type']);
        $this->assertEquals('LUCÍA', $segments[2]['speaker']);
        $this->assertEquals('Aoede', $segments[2]['voice']);
        $this->assertEquals('Un café solo, por favor.', $segments[2]['text']);
    }

    public function test_build_manifest_for_scope(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'stage' => 'raw',
            'raw_text' => 'Una persecución a toda velocidad por la autopista.',
        ]);

        $manifest = $this->service->buildManifestForScope($project, $scene->id);

        $this->assertCount(1, $manifest);
        $this->assertEquals($block->id, $manifest[0]['block_id']);
        $this->assertEquals('narrator', $manifest[0]['type']);
        $this->assertStringContainsString('persecución', $manifest[0]['text']);
        $this->assertStringContainsString('/tts/stream', $manifest[0]['stream_url']);
    }
}
