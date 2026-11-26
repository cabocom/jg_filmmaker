<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Broadcast\AutoGenerateBroadcastShotsAction;
use App\DTOs\Broadcast\BroadcastJobRequestDTO;
use App\Enums\BlockStage;
use App\Enums\ProjectCategory;
use App\Livewire\Projects\ProjectList;
use App\Models\Project;
use App\Models\User;
use App\Services\JgAgente\HmacSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectCategoriesAndApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.jg_agente.secret', 'test-projects-api-secret-1234567890');
    }

    public function test_projects_default_to_personal_category_and_enum_cast_works(): void
    {
        $user = User::factory()->create();

        $defaultProject = Project::create([
            'user_id' => $user->id,
            'title' => 'Proyecto Sin Categoría Explícita',
            'status' => 'draft',
        ]);
        $defaultProject->refresh();

        $this->assertSame(ProjectCategory::PERSONAL, $defaultProject->category);
        $this->assertSame(ProjectCategory::PERSONAL, $defaultProject->categoryEnum());

        $aiVideoProject = Project::factory()->for($user)->aiVideo()->create([
            'title' => 'Vídeo IA Generado',
        ]);
        $aiVideoProject->refresh();

        $this->assertSame(ProjectCategory::AI_VIDEO, $aiVideoProject->category);
        $this->assertSame(ProjectCategory::AI_VIDEO, $aiVideoProject->categoryEnum());

        $filteredIds = Project::query()->category(ProjectCategory::AI_VIDEO)->pluck('id')->all();
        $this->assertSame([$aiVideoProject->id], $filteredIds);
    }

    public function test_projects_api_rejects_missing_or_invalid_hmac_headers(): void
    {
        $payload = [
            'title' => 'Proyecto No Autorizado',
            'category' => 'ai_video',
        ];

        $noHeadersList = $this->getJson('/api/v1/projects');
        $noHeadersList->assertStatus(401);
        $noHeadersList->assertJson(['error' => 'Cabeceras de autenticación HMAC inválidas o ausentes']);

        $noHeadersStore = $this->postJson('/api/v1/projects', $payload);
        $noHeadersStore->assertStatus(401);
        $noHeadersStore->assertJson(['error' => 'Cabeceras de autenticación HMAC inválidas o ausentes']);

        $invalidHeaders = [
            'X-Signature' => 'invalid-signature-0000',
            'X-Timestamp' => (string) time(),
            'X-Nonce' => (string) Str::uuid(),
        ];

        $invalidSigStore = $this->postJson('/api/v1/projects', $payload, $invalidHeaders);
        $invalidSigStore->assertStatus(401);
        $invalidSigStore->assertJson(['error' => 'Firma HMAC inválida']);
    }

    public function test_projects_api_creates_ai_video_project_with_initial_scene_and_block(): void
    {
        $payload = [
            'title' => 'Documental Cuántico IA',
            'synopsis' => 'Un recorrido visual por la computación cuántica.',
            'status' => 'in_progress',
            'category' => 'ai_video',
            'storyboard_style' => 'cinematic_realistic',
            'raw_text' => 'La computación cuántica transforma la simulación molecular.',
            'slugline' => 'INT. LABORATORIO CUÁNTICO - DÍA',
        ];

        $headers = $this->generateHmacHeaders('POST', '/api/v1/projects', $payload);
        $response = $this->postJson('/api/v1/projects', $payload, $headers);

        $response->assertStatus(201);
        $response->assertJsonPath('title', 'Documental Cuántico IA');
        $response->assertJsonPath('category', 'ai_video');
        $response->assertJsonPath('category_label', 'Videos IA');
        $response->assertJsonPath('scenes_count', 1);

        $projectId = (string) $response->json('id');
        $this->assertStringContainsString("/projects/{$projectId}", (string) $response->json('studio_url'));

        /** @var Project $project */
        $project = Project::query()->with('scenes.scriptBlocks')->findOrFail($projectId);

        $this->assertSame(ProjectCategory::AI_VIDEO, $project->category);
        $this->assertCount(1, $project->scenes);

        $scene = $project->scenes->first();
        $this->assertNotNull($scene);
        $this->assertSame('INT. LABORATORIO CUÁNTICO - DÍA', $scene->slugline);
        $this->assertCount(1, $scene->scriptBlocks);

        $block = $scene->scriptBlocks->first();
        $this->assertNotNull($block);
        $this->assertSame(BlockStage::RAW->value, $block->stage);
        $this->assertSame('La computación cuántica transforma la simulación molecular.', $block->raw_text);
    }

    public function test_projects_api_lists_filters_shows_and_updates_projects(): void
    {
        $user = User::factory()->create();
        $personalProject = Project::factory()->for($user)->create([
            'title' => 'Cortometraje Personal',
            'category' => ProjectCategory::PERSONAL->value,
        ]);
        $aiProject = Project::factory()->for($user)->aiVideo()->create([
            'title' => 'Cápsula Informativa IA',
            'status' => 'draft',
        ]);

        // 1. List filtered by ai_video
        $listHeaders = $this->generateHmacHeaders('GET', '/api/v1/projects');
        $listResponse = $this->getJson('/api/v1/projects?category=ai_video', $listHeaders);
        $listResponse->assertStatus(200);
        $listResponse->assertJsonPath('meta.total', 1);
        $listResponse->assertJsonPath('data.0.id', $aiProject->id);
        $listResponse->assertJsonPath('data.0.category', 'ai_video');

        // 2. Invalid category filter -> 422
        $invalidFilterHeaders = $this->generateHmacHeaders('GET', '/api/v1/projects');
        $invalidFilterResponse = $this->getJson('/api/v1/projects?category=unknown_category', $invalidFilterHeaders);
        $invalidFilterResponse->assertStatus(422);
        $invalidFilterResponse->assertJson(['error' => 'Invalid project category']);

        // 3. Show existing project -> 200
        $showHeaders = $this->generateHmacHeaders('GET', "/api/v1/projects/{$personalProject->id}");
        $showResponse = $this->getJson("/api/v1/projects/{$personalProject->id}", $showHeaders);
        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('id', $personalProject->id);
        $showResponse->assertJsonPath('category', 'personal');

        // 4. Show missing project -> 404
        $missingId = (string) Str::uuid();
        $missingHeaders = $this->generateHmacHeaders('GET', "/api/v1/projects/{$missingId}");
        $missingResponse = $this->getJson("/api/v1/projects/{$missingId}", $missingHeaders);
        $missingResponse->assertStatus(404);
        $missingResponse->assertJson(['error' => 'Project not found']);

        // 5. Update project -> 200
        $updatePayload = [
            'title' => 'Cápsula Informativa IA Actualizada',
            'status' => 'completed',
        ];
        $updateHeaders = $this->generateHmacHeaders('PATCH', "/api/v1/projects/{$aiProject->id}", $updatePayload);
        $updateResponse = $this->patchJson("/api/v1/projects/{$aiProject->id}", $updatePayload, $updateHeaders);
        $updateResponse->assertStatus(200);
        $updateResponse->assertJsonPath('title', 'Cápsula Informativa IA Actualizada');
        $updateResponse->assertJsonPath('status', 'completed');

        $aiProject->refresh();
        $this->assertSame('Cápsula Informativa IA Actualizada', $aiProject->title);
        $this->assertSame('completed', $aiProject->status);
    }

    public function test_broadcast_job_initialization_assigns_ai_video_category_to_project(): void
    {
        Queue::fake();

        $dto = new BroadcastJobRequestDTO(
            channelId: 'ch-ai-news',
            format: 'news',
            title: 'Noticia Broadcast IA',
            rawText: 'Texto fuente de prueba para verificar la categoría ai_video en broadcast.',
            visualMode: 'cinematic_realistic',
        );

        /** @var AutoGenerateBroadcastShotsAction $action */
        $action = app(AutoGenerateBroadcastShotsAction::class);
        $job = $action->initialize($dto);

        $this->assertNotNull($job->project);
        $this->assertSame(ProjectCategory::AI_VIDEO, $job->project->category);
    }

    public function test_project_list_filters_by_category_and_ai_videos_do_not_exhaust_personal_slots(): void
    {
        $user = User::factory()->create([
            'paid_project_slots' => 5,
        ]);

        Project::factory()->for($user)->count(5)->aiVideo()->create([
            'title' => 'Video IA de Muestra',
        ]);

        $personalProject = Project::factory()->for($user)->create([
            'title' => 'Mi Largometraje Personal',
            'category' => ProjectCategory::PERSONAL->value,
        ]);

        $this->actingAs($user);

        Livewire::test(ProjectList::class)
            ->call('setCategoryFilter', 'ai_video')
            ->assertSet('categoryFilter', 'ai_video')
            ->assertSee('Video IA de Muestra')
            ->assertDontSee($personalProject->title)
            ->call('setCategoryFilter', 'personal')
            ->assertSet('categoryFilter', 'personal')
            ->assertSee($personalProject->title)
            ->assertDontSee('Video IA de Muestra');

        // Creating a personal project must succeed even with 5 ai_video projects
        Livewire::test(ProjectList::class)
            ->set('title', 'Segundo Proyecto Personal')
            ->set('synopsis', 'Sinopsis de prueba')
            ->set('status', 'draft')
            ->set('category', 'personal')
            ->call('createProject')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertSame(
            2,
            $user->projects()->where('category', ProjectCategory::PERSONAL->value)->count()
        );
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
