<?php

namespace Tests\Feature;

use App\Jobs\PrewarmScopeTtsJob;
use App\Livewire\Studio\ScriptEditor;
use App\Models\Character;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\TextToSpeechService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TextToSpeechServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TextToSpeechService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.google_ai.api_key' => 'testing_google_ai_key']);
        $this->service = new TextToSpeechService;

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
        ]);
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

    public function test_automatic_voice_assignment_persists_to_character_in_bible(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);

        // Personaje sin voice_id en custom_attributes
        $edurne = Character::factory()->create([
            'project_id' => $project->id,
            'name' => 'Edurne',
            'custom_attributes' => [],
        ]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'stage' => 'screenplay',
            'screenplay_content' => "EDURNE\nHola, estamos grabando.",
        ]);

        $this->assertNull($edurne->fresh()->custom_attributes['voice_id'] ?? null);

        // Al generar el manifiesto o parsear para locución
        $manifest = $this->service->buildManifestForScope($project, $scene->id);

        $this->assertNotEmpty($manifest);
        // La voz de Edurne (femenina) se asignó automáticamente y se guardó en la base de datos
        $refreshedEdurne = $edurne->fresh();
        $this->assertNotNull($refreshedEdurne->custom_attributes['voice_id'] ?? null);
        $this->assertEquals('Aoede', $refreshedEdurne->custom_attributes['voice_id']);
    }

    public function test_expand_screenplay_abbreviations(): void
    {
        $input = "INT. HABITACIÓN - DÍA\nJuan mira por la ventana.\nEXT. CALLE - NOCHE\nLa lluvia cae.\nINT./EXT. COCHE - TARDE\nArranca el motor.";
        $expanded = $this->service->expandScreenplayAbbreviations($input);

        $this->assertStringContainsString('Interior, HABITACIÓN - DÍA', $expanded);
        $this->assertStringContainsString('Exterior, CALLE - NOCHE', $expanded);
        $this->assertStringContainsString('Interior y exterior, COCHE - TARDE', $expanded);
    }

    public function test_prewarm_manifest_synthesizes_and_caches_segments(): void
    {
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
        ]);

        $id = uniqid();
        $manifest = [
            [
                'block_id' => 'b1',
                'scene_id' => 's1',
                'type' => 'narrator',
                'speaker' => 'NARRADOR',
                'voice' => 'Puck',
                'text' => "El sol se oculta en el horizonte lejano {$id}.",
                'stream_url' => '/test/1',
            ],
            [
                'block_id' => 'b2',
                'scene_id' => 's1',
                'type' => 'dialogue',
                'speaker' => 'EDURNE',
                'voice' => 'Aoede',
                'text' => "Ya no queda tiempo para volver {$id}.",
                'stream_url' => '/test/2',
            ],
        ];

        $stats = $this->service->prewarmManifest($manifest);

        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(2, $stats['synthesized']);
        $this->assertEquals(0, $stats['cached']);
        $this->assertEquals(0, $stats['failed']);

        // Segunda ejecución sobre el mismo manifiesto: todos deben venir de caché
        $statsSecond = $this->service->prewarmManifest($manifest);
        $this->assertEquals(2, $statsSecond['cached']);
        $this->assertEquals(0, $statsSecond['synthesized']);
    }

    public function test_prewarm_scope_tts_job_and_commands(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);
        ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'stage' => 'screenplay',
            'screenplay_content' => "INT. SALA - DÍA\n\nJUAN\nLlegamos a tiempo.",
        ]);

        // Job
        PrewarmScopeTtsJob::dispatchSync($project, $scene->id);

        // Command tts:prewarm
        $this->artisan('tts:prewarm', [
            'project' => $project->id,
            '--sync' => true,
        ])->assertExitCode(0);

        // Command tts:prune-orphans
        $this->artisan('tts:prune-orphans', [
            '--dry-run' => true,
        ])->assertExitCode(0);
    }

    public function test_is_project_audio_complete_and_export_mp3(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'title' => 'Historia Épica']);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);
        ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'stage' => 'screenplay',
            'screenplay_content' => "INT. NAVE - NOCHE\n\nMARIA\nTexto ".Str::uuid().' para prueba mp3.',
        ]);

        // 1. Al inicio, sin sintetizar, el audio está incompleto
        $this->assertFalse($this->service->isProjectAudioComplete($project));

        // 2. Ruta export-mp3 debe retornar error 400 si faltan audios
        $this->actingAs($user)
            ->get(route('projects.export-mp3', $project))
            ->assertStatus(400);

        // 3. Sintetizamos todos los segmentos del manifiesto
        $manifest = $this->service->buildManifestForScope($project);
        $this->service->prewarmManifest($manifest);

        // 4. Ahora debe reportar que el audio está completo
        $this->assertTrue($this->service->isProjectAudioComplete($project));

        // 5. Simular llamada a FFmpeg para exportar MP3
        Process::fake([
            '*' => function (PendingProcess $process) {
                $cmd = $process->command;
                $outputArg = is_array($cmd) ? end($cmd) : null;
                if ($outputArg) {
                    file_put_contents($outputArg, 'ID3_FAKE_MP3_CONTENT');
                }

                return Process::result(output: 'ffmpeg success', exitCode: 0);
            },
        ]);

        $mp3Path = $this->service->exportProjectToMp3($project);
        $this->assertFileExists($mp3Path);
        $this->assertGreaterThan(0, filesize($mp3Path));

        // 6. Descarga mediante la ruta del controlador
        $response = $this->actingAs($user)
            ->get(route('projects.export-mp3', $project));
        $response->assertOk();
        $this->assertEquals('audio/mpeg', $response->headers->get('Content-Type'));

        // 7. Descarga mediante Livewire ScriptEditor
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->assertSee('MP3')
            ->assertSee('PDF')
            ->call('exportMp3')
            ->assertFileDownloaded('historia-epica-locucion-completa.mp3');

        if (file_exists($mp3Path)) {
            @unlink($mp3Path);
        }
    }
}
