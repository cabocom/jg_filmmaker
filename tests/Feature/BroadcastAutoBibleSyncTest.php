<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Broadcast\AutoGenerateBroadcastShotsAction;
use App\Actions\Broadcast\SyncBroadcastProjectBibleAction;
use App\DTOs\Broadcast\BroadcastJobRequestDTO;
use App\Enums\BroadcastJobStage;
use App\Enums\ProjectCategory;
use App\Models\BroadcastJob;
use App\Models\Character;
use App\Models\Location;
use App\Models\Shot;
use App\Services\JgAgente\AgentClientService;
use App\Services\JgAgente\HmacSigner;
use App\Services\StoryboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BroadcastAutoBibleSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.jg_agente.secret', 'test-broadcast-bible-secret-1234567890');
    }

    public function test_sync_action_creates_location_links_scene_and_seeds_default_presenter_when_no_characters_found(): void
    {
        Queue::fake();

        $mockAgent = Mockery::mock(AgentClientService::class);
        $this->app->instance(AgentClientService::class, $mockAgent);

        $mockAgent->shouldReceive('getLastTelemetry')->andReturn(['model' => 'test-llm']);
        $mockAgent->shouldReceive('extractCharacters')->once()->andReturn([]);

        $dto = BroadcastJobRequestDTO::fromArray([
            'channel_id' => 'ch-expository',
            'format' => 'news',
            'title' => 'Panorama Tecnológico Europeo',
            'raw_text' => 'Los centros de investigación europeos han publicado nuevos datos sobre eficiencia energética en redes ópticas.',
        ]);

        $job = app(AutoGenerateBroadcastShotsAction::class)->initialize($dto);

        $summary = app(SyncBroadcastProjectBibleAction::class)->execute($job);

        $project = $job->project->fresh(['characters', 'locations', 'scenes']);
        $scene = $project->scenes->first();

        $this->assertNotNull($scene);
        $this->assertNotNull($scene->location_id);
        $this->assertSame(1, $summary['characters_count']);
        $this->assertSame(1, $summary['locations_count']);
        $this->assertContains('Presentador', $summary['characters']);
        $this->assertDatabaseHas('characters', [
            'project_id' => $project->id,
            'name' => 'Presentador',
        ]);
        $this->assertDatabaseHas('locations', [
            'project_id' => $project->id,
            'id' => $scene->location_id,
        ]);
    }

    public function test_broadcast_api_accepts_cast_and_location_profiles_and_populates_project_bible(): void
    {
        Queue::fake();

        $mockAgent = Mockery::mock(AgentClientService::class);
        $this->app->instance(AgentClientService::class, $mockAgent);

        $mockAgent->shouldReceive('getLastTelemetry')->andReturn(['model' => 'test-llm']);
        $mockAgent->shouldReceive('extractCharacters')->once()->andReturn([]);

        $payload = [
            'channel_id' => 'ch-ai-today',
            'format' => 'news',
            'title' => 'Edición Especial Inteligencia Artificial',
            'raw_text' => 'Elena Vance analiza los últimos avances en modelos multimodales aplicados a producción audiovisual.',
            'cast_profiles' => [
                [
                    'name' => 'Elena Vance',
                    'bio' => 'Periodista científica y presentadora titular.',
                    'costume_notes' => 'Traje sastre azul marino con solapa minimalista',
                ],
            ],
            'location_profile' => [
                'name' => 'PLATO AI TODAY',
                'type' => 'INT',
                'lighting_notes' => 'Iluminación key light suave con acentos cian en fondo',
                'art_department_notes' => 'Mesa curva de cristal y pantallas LED de alta resolución',
            ],
        ];

        $headers = $this->generateHmacHeaders('POST', '/api/v1/broadcast/jobs', $payload);
        $response = $this->postJson('/api/v1/broadcast/jobs', $payload, $headers);

        $response->assertStatus(202);

        $jobId = (string) $response->json('job_id');
        $job = BroadcastJob::findOrFail($jobId);
        $project = $job->project;
        $scene = $project->scenes()->firstOrFail();

        $character = Character::where('project_id', $project->id)
            ->where('name', 'Elena Vance')
            ->first();
        $this->assertNotNull($character);
        $this->assertSame('Traje sastre azul marino con solapa minimalista', $character->costume_notes);

        $location = Location::where('project_id', $project->id)
            ->where('name', 'PLATO AI TODAY')
            ->first();
        $this->assertNotNull($location);
        $this->assertSame('Iluminación key light suave con acentos cian en fondo', $location->lighting_notes);
        $this->assertSame('Mesa curva de cristal y pantallas LED de alta resolución', $location->art_department_notes);
        $this->assertSame($location->id, $scene->location_id);

        $this->assertIsArray($job->artifacts['bible_summary'] ?? null);
        $this->assertGreaterThanOrEqual(1, $job->artifacts['bible_summary']['characters_count']);
        $this->assertGreaterThanOrEqual(1, $job->artifacts['bible_summary']['locations_count']);
    }

    public function test_treatment_and_screenplay_stages_sync_bible_and_tolerate_extractor_errors(): void
    {
        Queue::fake();

        $mockAgent = Mockery::mock(AgentClientService::class);
        $this->app->instance(AgentClientService::class, $mockAgent);

        $mockAgent->shouldReceive('getLastTelemetry')->andReturn(['model' => 'test-llm']);
        $mockAgent->shouldReceive('structureTreatment')
            ->once()
            ->andReturn("DRA. SOLER\nPresenta el avance cuántico.");
        $mockAgent->shouldReceive('formatScreenplay')
            ->once()
            ->andReturn("DRA. SOLER\nEl avance cuántico ya es una realidad en los laboratorios.");
        $mockAgent->shouldReceive('extractCharacters')
            ->twice()
            ->andThrow(new RuntimeException('Timeout'));

        $action = app(AutoGenerateBroadcastShotsAction::class);
        $dto = BroadcastJobRequestDTO::fromArray([
            'channel_id' => 'ch-resilient-bible',
            'format' => 'news',
            'title' => 'Avance Cuántico',
            'raw_text' => 'Resumen de investigación sobre computación cuántica tolerante a fallos.',
        ]);

        $job = $action->initialize($dto);

        $jobAfterTreatment = $action->executeStage($job, BroadcastJobStage::TREATMENT);

        $this->assertSame(BroadcastJobStage::TREATMENT, $jobAfterTreatment->stage);
        $this->assertIsArray($jobAfterTreatment->artifacts['bible_summary'] ?? null);
        $this->assertGreaterThanOrEqual(1, $jobAfterTreatment->artifacts['bible_summary']['characters_count']);
        $this->assertGreaterThanOrEqual(1, $jobAfterTreatment->artifacts['bible_summary']['locations_count']);
        $this->assertGreaterThanOrEqual(1, $jobAfterTreatment->project->characters()->count());
        $this->assertGreaterThanOrEqual(1, $jobAfterTreatment->project->locations()->count());

        $jobAfterScreenplay = $action->executeStage($jobAfterTreatment, BroadcastJobStage::SCREENPLAY);

        $this->assertSame(BroadcastJobStage::SCREENPLAY, $jobAfterScreenplay->stage);
        $this->assertIsArray($jobAfterScreenplay->artifacts['bible_summary'] ?? null);
        $this->assertGreaterThanOrEqual(1, $jobAfterScreenplay->artifacts['bible_summary']['characters_count']);
    }

    public function test_storyboard_prompt_builder_uses_auto_synced_bible_for_ai_video_shots(): void
    {
        Queue::fake();

        $mockAgent = Mockery::mock(AgentClientService::class);
        $this->app->instance(AgentClientService::class, $mockAgent);

        $mockAgent->shouldReceive('getLastTelemetry')->andReturn(['model' => 'test-llm']);
        $mockAgent->shouldReceive('extractCharacters')->andReturn([]);

        $dto = BroadcastJobRequestDTO::fromArray([
            'channel_id' => 'ch-storyboard-bible',
            'format' => 'news',
            'title' => 'Crónica Espacial',
            'raw_text' => 'Elena Vance explica los detalles de la nueva misión orbital.',
            'cast_profiles' => [
                [
                    'name' => 'Elena Vance',
                    'costume_notes' => 'Chaqueta técnica gris grafito',
                ],
            ],
            'location_profile' => [
                'name' => 'OBSERVATORIO ORBITAL',
                'type' => 'INT',
                'lighting_notes' => 'Luz fría estelar con reflejos ámbar en consola',
            ],
        ]);

        $job = app(AutoGenerateBroadcastShotsAction::class)->initialize($dto);
        $project = $job->project->fresh();

        $this->assertSame(ProjectCategory::AI_VIDEO, $project->categoryEnum());

        $scene = $project->scenes()->firstOrFail();
        $shot = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $job->script_block_id,
            'shot_number' => 1,
            'shot_type' => 'PM',
            'description' => 'Elena Vance habla a cámara junto a la consola central.',
        ]);

        $prompt = app(StoryboardService::class)->buildPrompt($shot);

        $this->assertStringContainsString('Character Consistency:', $prompt);
        $this->assertStringContainsString('Elena Vance', $prompt);
        $this->assertStringContainsString('Chaqueta técnica gris grafito', $prompt);
        $this->assertStringContainsString('Location & Scenography:', $prompt);
        $this->assertStringContainsString('OBSERVATORIO ORBITAL', $prompt);
        $this->assertStringContainsString('Luz fría estelar con reflejos ámbar en consola', $prompt);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, string>
     */
    private function generateHmacHeaders(string $method, string $path, ?array $payload = null): array
    {
        $rawBody = $payload !== null ? (string) json_encode($payload) : '';
        $secret = (string) config('services.jg_agente.secret');

        return (new HmacSigner)->generateSignatureHeaders(
            method: $method,
            path: $path,
            rawBody: $rawBody,
            secret: $secret,
        );
    }
}
