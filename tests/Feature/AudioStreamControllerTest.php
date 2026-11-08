<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudioStreamControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_manifest_or_stream(): void
    {
        $project = Project::factory()->create();

        $response = $this->getJson(route('projects.tts.manifest', ['project' => $project->id]));
        $response->assertUnauthorized();

        $streamResponse = $this->get(route('projects.tts.stream', ['project' => $project->id]));
        $streamResponse->assertRedirect(route('login'));
    }

    public function test_user_cannot_access_other_users_project_tts(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $project = Project::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger);

        $response = $this->getJson(route('projects.tts.manifest', ['project' => $project->id]));
        $response->assertForbidden();

        $streamResponse = $this->get(route('projects.tts.stream', [
            'project' => $project->id,
            'block_id' => '00000000-0000-0000-0000-000000000000',
        ]));
        $streamResponse->assertForbidden();
    }

    public function test_owner_can_get_tts_manifest_and_stream_audio(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);
        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'stage' => 'raw',
            'raw_text' => 'Texto de prueba para síntesis de voz.',
        ]);

        $this->actingAs($owner);

        $manifestResponse = $this->getJson(route('projects.tts.manifest', [
            'project' => $project->id,
            'block_id' => $block->id,
        ]));

        $manifestResponse->assertOk();
        $manifestResponse->assertJsonStructure([
            'status',
            'project_id',
            'count',
            'manifest' => [
                '*' => ['block_id', 'scene_id', 'type', 'speaker', 'voice', 'text', 'stream_url'],
            ],
        ]);

        $this->assertEquals(1, $manifestResponse->json('count'));

        // Probar el endpoint de streaming del segmento
        $streamResponse = $this->get(route('projects.tts.stream', [
            'project' => $project->id,
            'block_id' => $block->id,
            'segment_index' => 0,
        ]));

        $streamResponse->assertOk();
        $streamResponse->assertHeader('Content-Type', 'audio/wav');
        $streamResponse->assertHeader('Accept-Ranges', 'bytes');
    }

    public function test_owner_can_listen_to_voice_demo(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);

        // Demo con voz por defecto
        $response = $this->get(route('projects.tts.demo-voice', [
            'project' => $project->id,
            'voice' => 'sharvard#M',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'audio/wav');
        $response->assertHeader('Accept-Ranges', 'bytes');

        // Demo con voz específica (davefx)
        $responseDave = $this->get(route('projects.tts.demo-voice', [
            'project' => $project->id,
            'voice' => 'davefx',
        ]));

        $responseDave->assertOk();
        $responseDave->assertHeader('Content-Type', 'audio/wav');
    }

    public function test_unauthenticated_user_cannot_access_demo_voice(): void
    {
        $project = Project::factory()->create();

        $response = $this->get(route('projects.tts.demo-voice', [
            'project' => $project->id,
            'voice' => 'sharvard#M',
        ]));

        $response->assertRedirect(route('login'));
    }
}
