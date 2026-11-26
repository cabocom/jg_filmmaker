<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\Storyboard\VisualAestheticConfigDTO;
use App\Enums\BroadcastJobStage;
use App\Enums\ProjectCategory;
use App\Livewire\Studio\ScriptEditor;
use App\Models\BroadcastJob;
use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\User;
use App\Services\Broadcast\Visuals\StoryboardShotVisual;
use App\Services\JgAgente\AgentClientService;
use App\Services\StoryboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ShotAestheticControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_frame_prompt_builder_produces_clean_prose_without_storyboard_headers(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->aiVideo()->create([
            'user_id' => $user->id,
            'storyboard_style' => 'editorial_documentary_35mm',
        ]);

        $character = Character::create([
            'project_id' => $project->id,
            'name' => 'Elena Vance',
            'bio' => 'Periodista científica.',
            'costume_notes' => 'Traje sastre azul marino',
        ]);

        $location = Location::create([
            'project_id' => $project->id,
            'name' => 'PLATO AI TODAY',
            'type' => 'INT',
            'art_department_notes' => 'Mesa curva de cristal',
        ]);

        $scene = Scene::create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. PLATO AI TODAY - DIA',
            'location_id' => $location->id,
            'time_of_day' => 'DIA',
        ]);

        $shot = Shot::create([
            'scene_id' => $scene->id,
            'shot_number' => 1,
            'shot_type' => 'PM',
            'description' => 'Elena Vance presenta el informe técnico frente a cámara.',
        ]);

        $config = new VisualAestheticConfigDTO(
            renderMode: VisualAestheticConfigDTO::MODE_FULL_BLEED,
            colorPalette: 'navy and warm amber',
            lightingStyle: 'volumetric soft light',
            cameraLens: '35mm anamorphic',
        );

        $prompt = app(StoryboardService::class)->buildPrompt(
            shot: $shot,
            style: 'editorial_documentary_35mm',
            characterId: $character->id,
            locationId: $location->id,
            aestheticConfig: $config,
        );

        $this->assertStringNotContainsString('ESCENA 1:', $prompt);
        $this->assertStringNotContainsString('Action & Composition:', $prompt);
        $this->assertStringNotContainsString('Cinematic film storyboard panel', $prompt);

        $this->assertStringContainsString('Elena Vance', $prompt);
        $this->assertStringContainsString('Traje sastre azul marino', $prompt);
        $this->assertStringContainsString('PLATO AI TODAY', $prompt);
        $this->assertStringContainsString('Mesa curva de cristal', $prompt);
        $this->assertStringContainsString('navy and warm amber', $prompt);
        $this->assertStringContainsString('volumetric soft light', $prompt);
        $this->assertStringContainsString('35mm anamorphic', $prompt);
    }

    public function test_storyboard_service_forwards_aesthetic_config_and_continuity_anchor_and_persists_on_shot(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::factory()->aiVideo()->create([
            'user_id' => $user->id,
        ]);

        $scene = Scene::create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. ESTUDIO - DIA',
            'time_of_day' => 'DIA',
        ]);

        $shot = Shot::create([
            'scene_id' => $scene->id,
            'shot_number' => 2,
            'shot_type' => 'PG',
            'description' => 'Plano general del estudio iluminado.',
        ]);

        $config = new VisualAestheticConfigDTO(
            renderMode: VisualAestheticConfigDTO::MODE_FULL_BLEED,
            colorPalette: 'slate blue and amber',
            lightingStyle: 'soft studio key light',
            cameraLens: '50mm prime',
            negativeDirectives: 'no text overlays',
        );

        $anchorBase64 = base64_encode('anchor-image-bytes');
        $generatedBase64 = base64_encode('generated-frame-bytes-12345');

        $mockAgent = Mockery::mock(AgentClientService::class);
        $this->app->instance(AgentClientService::class, $mockAgent);

        $mockAgent->shouldReceive('callTool')
            ->once()
            ->withArgs(function (string $tool, array $payload) use ($anchorBase64): bool {
                return $tool === 'generate_storyboard'
                    && ($payload['render_mode'] ?? null) === 'full_bleed_frame'
                    && ($payload['color_palette'] ?? null) === 'slate blue and amber'
                    && ($payload['lighting_style'] ?? null) === 'soft studio key light'
                    && ($payload['camera_lens'] ?? null) === '50mm prime'
                    && ($payload['negative_directives'] ?? null) === 'no text overlays'
                    && ($payload['style_reference_image_base64'] ?? null) === $anchorBase64;
            })
            ->andReturn([
                'success' => true,
                'metadata' => [
                    'image_base64' => $generatedBase64,
                    'mime_type' => 'image/png',
                ],
            ]);

        $relativePath = app(StoryboardService::class)->generateStoryboard(
            shot: $shot,
            style: 'editorial_documentary_35mm',
            aestheticConfig: $config,
            styleReferenceImageBase64: $anchorBase64,
        );

        Storage::disk('local')->assertExists($relativePath);

        $freshShot = $shot->fresh();
        $this->assertNotNull($freshShot);
        $this->assertSame('full_bleed_frame', $freshShot->visual_aesthetic_config['render_mode'] ?? null);
        $this->assertSame('slate blue and amber', $freshShot->visual_aesthetic_config['color_palette'] ?? null);
        $this->assertSame('soft studio key light', $freshShot->visual_aesthetic_config['lighting_style'] ?? null);
        $this->assertSame('50mm prime', $freshShot->visual_aesthetic_config['camera_lens'] ?? null);
        $this->assertSame('no text overlays', $freshShot->visual_aesthetic_config['negative_directives'] ?? null);
    }

    public function test_storyboard_shot_visual_reuses_studio_approved_image_and_defaults_missing_shots_to_full_bleed(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::factory()->aiVideo()->create([
            'user_id' => $user->id,
        ]);

        $scene = Scene::create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. REDACCION - DIA',
            'time_of_day' => 'DIA',
        ]);

        $anchorRelativePath = "media/projects/{$project->id}/storyboards/scene_{$scene->id}/shot_1.png";
        $anchorBytes = 'approved-anchor-png-bytes';
        Storage::disk('local')->put($anchorRelativePath, $anchorBytes);

        $shot1 = Shot::create([
            'scene_id' => $scene->id,
            'shot_number' => 1,
            'shot_type' => 'PM',
            'description' => 'Plano inicial aprobado en estudio.',
            'storyboard_image_path' => $anchorRelativePath,
        ]);

        $shot2 = Shot::create([
            'scene_id' => $scene->id,
            'shot_number' => 2,
            'shot_type' => 'PP',
            'description' => 'Segundo plano sin imagen previa.',
        ]);

        $job = BroadcastJob::create([
            'channel_id' => 'ch-test',
            'format' => 'news',
            'title' => 'Boletín Especial',
            'raw_text' => 'Contenido de prueba para emisión.',
            'stage' => BroadcastJobStage::SHOTS,
            'project_id' => $project->id,
            'visual_mode' => 'storyboard',
        ]);

        // Caso A: Shot 1 ya tiene imagen aprobada -> reutiliza directamente sin llamar a StoryboardService
        $mockServiceA = Mockery::mock(StoryboardService::class);
        $mockServiceA->shouldNotReceive('generateStoryboard');
        $this->app->instance(StoryboardService::class, $mockServiceA);

        $visualA = app(StoryboardShotVisual::class);
        $resultPathA = $visualA->generateVisual($job, $shot1);
        $this->assertSame(Storage::disk('local')->path($anchorRelativePath), $resultPathA);

        // Caso B: Shot 2 carece de imagen -> invoca StoryboardService con full_bleed_frame y ancla de continuidad de Shot 1
        $generatedRelativePath = "media/projects/{$project->id}/storyboards/scene_{$scene->id}/shot_2.png";
        Storage::disk('local')->put($generatedRelativePath, 'generated-shot-2-bytes');

        $mockServiceB = Mockery::mock(StoryboardService::class);
        $mockServiceB->shouldReceive('generateStoryboard')
            ->once()
            ->withArgs(function (
                Shot $passedShot,
                ?string $style,
                ?string $customPrompt,
                ?string $characterId,
                ?string $locationId,
                array $propImages,
                ?VisualAestheticConfigDTO $aestheticConfig,
                ?string $styleReferenceImageBase64,
            ) use ($shot2, $anchorBytes): bool {
                return $passedShot->is($shot2)
                    && $style === 'editorial_documentary_35mm'
                    && $aestheticConfig instanceof VisualAestheticConfigDTO
                    && $aestheticConfig->renderMode === VisualAestheticConfigDTO::MODE_FULL_BLEED
                    && $styleReferenceImageBase64 === base64_encode($anchorBytes);
            })
            ->andReturn($generatedRelativePath);

        $this->app->instance(StoryboardService::class, $mockServiceB);

        $visualB = app(StoryboardShotVisual::class);
        $resultPathB = $visualB->generateVisual($job, $shot2);
        $this->assertSame(Storage::disk('local')->path($generatedRelativePath), $resultPathB);
    }

    public function test_script_editor_modal_supports_iterative_aesthetic_preview_without_closing(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'category' => ProjectCategory::AI_VIDEO,
        ]);

        $scene = Scene::create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. LABORATORIO - NOCHE',
            'time_of_day' => 'NOCHE',
        ]);

        $block = ScriptBlock::create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'Escena de laboratorio.',
        ]);

        $shot = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'PM',
            'description' => 'Investigador analizando pantallas holográficas.',
        ]);

        $mockAgent = Mockery::mock(AgentClientService::class);
        $this->app->instance(AgentClientService::class, $mockAgent);

        $mockAgent->shouldReceive('callTool')
            ->once()
            ->with('generate_storyboard', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'metadata' => [
                    'image_base64' => base64_encode('livewire-preview-png-bytes'),
                    'mime_type' => 'image/png',
                ],
            ]);

        $this->actingAs($user);

        Livewire::test(ScriptEditor::class, ['project' => $project])
            ->call('openStoryboardModal', $shot->id)
            ->assertSet('storyboardRenderMode', 'full_bleed_frame')
            ->set('storyboardColorPalette', 'emerald and warm gold')
            ->call('testGenerateStoryboardInModal')
            ->assertSet('showStoryboardModal', true)
            ->assertSet('storyboardPreviewVersion', 1);

        $freshShot = $shot->fresh();
        $this->assertNotNull($freshShot);
        $this->assertSame('emerald and warm gold', $freshShot->visual_aesthetic_config['color_palette'] ?? null);
    }
}
