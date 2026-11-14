<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\Tts\CharacterVoiceMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TtsReadSideEffectsAndUtf8Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_manifest_get_request_without_persist_flag_does_not_mutate_database(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);

        $edurne = Character::factory()->create([
            'project_id' => $project->id,
            'name' => 'Edurne',
            'custom_attributes' => [],
        ]);

        ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'stage' => 'screenplay',
            'screenplay_content' => "EDURNE\nHola, estamos grabando.\n\nLUCÍA\nPerfecto, todo listo.",
        ]);

        $this->actingAs($user)
            ->getJson(route('projects.tts.manifest', ['project' => $project->id]))
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        // Edurne no debe tener modificado su custom_attributes en BD
        $this->assertNull($edurne->fresh()->custom_attributes['voice_id'] ?? null);

        // LUCÍA no debe haberse creado en la base de datos
        $this->assertDatabaseMissing('characters', [
            'project_id' => $project->id,
            'name' => 'Lucía',
        ]);
        $this->assertEquals(1, Character::where('project_id', $project->id)->count());
    }

    public function test_manifest_get_request_with_persist_bible_true_creates_character_with_proper_utf8_title_case(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);

        ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'stage' => 'screenplay',
            'screenplay_content' => "LUCÍA\nUn café solo, por favor.",
        ]);

        $this->actingAs($user)
            ->getJson(route('projects.tts.manifest', [
                'project' => $project->id,
                'persist_bible' => 1,
            ]))
            ->assertOk();

        // Con persist_bible=1, LUCÍA debe persistirse como "Lucía" (UTF-8 válido)
        $char = Character::where('project_id', $project->id)->first();
        $this->assertNotNull($char);
        $this->assertEquals('Lucía', $char->name);
        $this->assertNotNull($char->custom_attributes['voice_id'] ?? null);
    }

    public function test_stream_audio_never_mutates_characters_or_creates_new_ones(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);

        $existingChar = Character::factory()->create([
            'project_id' => $project->id,
            'name' => 'Protagonista',
            'custom_attributes' => [],
        ]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'stage' => 'screenplay',
            'screenplay_content' => "ÍÑIGO\n¿Quién anda ahí?\n\nProtagonista\nSoy yo.",
        ]);

        $this->actingAs($user)
            ->get(route('projects.tts.stream', [
                'project' => $project->id,
                'block_id' => $block->id,
                'segment_index' => 0,
            ]))
            ->assertOk();

        // No se debe crear ÍÑIGO ni modificarse Protagonista
        $this->assertDatabaseMissing('characters', [
            'project_id' => $project->id,
            'name' => 'Íñigo',
        ]);
        $this->assertNull($existingChar->fresh()->custom_attributes['voice_id'] ?? null);
        $this->assertEquals(1, Character::where('project_id', $project->id)->count());
    }

    public function test_prune_orphans_dry_run_does_not_mutate_database(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);

        Character::factory()->create([
            'project_id' => $project->id,
            'name' => 'Personaje Sin Voz',
            'custom_attributes' => [],
        ]);

        ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'stage' => 'screenplay',
            'screenplay_content' => "NUEVO HABLANTE\nEsto es un test de purga.",
        ]);

        $initialCharactersCount = Character::count();

        $this->artisan('tts:prune-orphans', [
            '--days' => 0,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertEquals($initialCharactersCount, Character::count());
        $this->assertDatabaseMissing('characters', [
            'name' => 'Nuevo Hablante',
        ]);
    }

    public function test_spanish_utf8_casing_preserves_accents_and_special_characters(): void
    {
        $project = Project::factory()->create();
        $mapper = new CharacterVoiceMapper(hasGoogleApiKey: true);

        $mapper->persistSpeakerToBible($project, 'LUCÍA', 'Aoede', persistBibleChanges: true);
        $mapper->persistSpeakerToBible($project, 'ÍÑIGO', 'Charon', persistBibleChanges: true);
        $mapper->persistSpeakerToBible($project, 'ÁLVARO', 'Puck', persistBibleChanges: true);
        $mapper->persistSpeakerToBible($project, 'SEÑOR GÓMEZ', 'Fenrir', persistBibleChanges: true);
        $mapper->persistSpeakerToBible($project, 'ÓSCAR', 'Zephyr', persistBibleChanges: true);

        $names = Character::where('project_id', $project->id)->pluck('name')->all();

        $this->assertContains('Lucía', $names);
        $this->assertContains('Íñigo', $names);
        $this->assertContains('Álvaro', $names);
        $this->assertContains('Señor Gómez', $names);
        $this->assertContains('Óscar', $names);

        // Caso existente: actualizar voz de LUCÍA sin duplicar registro
        $mapper->persistSpeakerToBible($project, 'lucía', 'Kore', persistBibleChanges: true);
        $this->assertEquals(5, Character::where('project_id', $project->id)->count());
        $lucia = Character::where('project_id', $project->id)->where('name', 'Lucía')->first();
        $this->assertEquals('Kore', $lucia->custom_attributes['voice_id']);
    }
}
