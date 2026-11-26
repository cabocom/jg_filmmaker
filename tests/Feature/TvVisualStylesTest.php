<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Tv\TvRundownsPanel;
use App\Livewire\Tv\TvStylesPanel;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Shot;
use App\Models\TvRundown;
use App\Models\TvVisualStyle;
use App\Models\User;
use App\Services\Broadcast\Visuals\StoryboardShotVisual;
use App\Services\JgAgente\AgentClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TvVisualStylesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_set_default_and_delete_visual_style_while_non_admin_receives_403(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $this->actingAs($nonAdmin);

        Livewire::test(TvStylesPanel::class)
            ->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(TvStylesPanel::class)
            ->call('startCreate')
            ->set('name', 'Acuarela Editorial')
            ->set('promptBase', 'Editorial watercolor illustration, expressive brushwork, strictly non-photorealistic')
            ->set('characteristics', 'Acuarela suave sobre papel texturizado')
            ->set('isDefault', false)
            ->set('isActive', true)
            ->call('saveStyle')
            ->assertHasNoErrors();

        $style = TvVisualStyle::query()->where('name', 'Acuarela Editorial')->first();
        $this->assertNotNull($style);
        $this->assertFalse($style->is_default);

        Livewire::test(TvStylesPanel::class)
            ->call('setDefault', $style->id)
            ->assertHasNoErrors();

        $this->assertTrue($style->refresh()->is_default);

        Livewire::test(TvStylesPanel::class)
            ->call('deleteStyle', $style->id)
            ->assertHasNoErrors();

        $this->assertNull(TvVisualStyle::query()->find($style->id));
    }

    public function test_rundowns_panel_creates_rundown_and_links_visual_style_to_rundown_and_project(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $style = TvVisualStyle::create([
            'name' => 'Estilo Cómic Europeo',
            'slug' => 'estilo-comic-europeo',
            'prompt_base' => 'European graphic novel illustration, ligne claire ink outlines, no photorealism',
            'characteristics' => 'Tinta limpia y color plano',
            'is_default' => false,
            'is_active' => true,
        ]);

        Livewire::test(TvRundownsPanel::class)
            ->set('sourceKind', 'manual')
            ->set('title', 'Noticiero Estilo Cómic')
            ->set('scriptText', 'Guion de prueba para validar la vinculación de estilo visual.')
            ->set('rundownType', 'pill')
            ->set('language', 'es')
            ->set('ttsProvider', 'gemini')
            ->set('ttsVoice', 'Kore')
            ->set('visualStyleId', $style->id)
            ->call('createRundown')
            ->assertHasNoErrors();

        $rundown = TvRundown::query()->where('title', 'Noticiero Estilo Cómic')->first();
        $this->assertNotNull($rundown);
        $this->assertSame($style->id, $rundown->visual_style_id);

        $project = $rundown->project;
        $this->assertNotNull($project);
        $this->assertSame($style->id, $project->visual_style_id);
        $this->assertSame($style->prompt_base, $project->visual_aesthetic_config['style_prompt'] ?? null);
    }

    public function test_storyboard_shot_visual_applies_project_visual_style_prompt_and_reference_image_for_first_shot(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('tv-styles/style-ref.png', 'fake-style-ref-bytes');

        $style = TvVisualStyle::create([
            'name' => 'Grabado Editorial',
            'slug' => 'grabado-editorial',
            'prompt_base' => 'Custom ink storyboard aesthetic, strictly non-photorealistic',
            'characteristics' => 'High contrast ink crosshatching',
            'reference_image_path' => 'tv-styles/style-ref.png',
            'is_default' => true,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['is_admin' => true]);
        $project = Project::factory()->aiVideo()->create([
            'user_id' => $user->id,
            'visual_style_id' => $style->id,
        ]);
        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'scene_order' => 1,
        ]);
        $shot = Shot::factory()->create([
            'scene_id' => $scene->id,
            'shot_number' => 1,
            'shot_type' => 'PG',
            'description' => 'Un estudio minimalista iluminado',
        ]);
        $job = BroadcastJob::create([
            'channel_id' => 'ai-today',
            'format' => 'pill',
            'title' => 'Test Broadcast Job',
            'raw_text' => 'Texto de prueba',
            'language' => 'es',
            'visual_mode' => 'editorial_documentary_35mm',
            'project_id' => $project->id,
        ]);

        $capturedPayload = null;
        $this->mock(AgentClientService::class, function ($mock) use (&$capturedPayload): void {
            $mock->shouldReceive('callTool')
                ->once()
                ->withArgs(function (string $toolName, array $payload) use (&$capturedPayload): bool {
                    $capturedPayload = $payload;

                    return $toolName === 'generate_storyboard';
                })
                ->andReturn([
                    'success' => true,
                    'metadata' => [
                        'image_base64' => base64_encode('generated-storyboard-png-bytes'),
                        'mime_type' => 'image/png',
                    ],
                ]);
        });

        $resolvedPath = app(StoryboardShotVisual::class)->generateVisual($job, $shot);

        $this->assertNotNull($capturedPayload);
        $this->assertStringContainsString('Custom ink storyboard aesthetic, strictly non-photorealistic', $capturedPayload['prompt']);
        $this->assertStringContainsString('High contrast ink crosshatching', $capturedPayload['prompt']);
        $this->assertStringNotContainsString('Cinematic 35mm documentary photography still', $capturedPayload['prompt']);
        $this->assertSame(base64_encode('fake-style-ref-bytes'), $capturedPayload['style_reference_image_base64']);

        $shot->refresh();
        $this->assertNotEmpty($shot->storyboard_image_path);
        $this->assertStringContainsString('Custom ink storyboard aesthetic', (string) $shot->storyboard_prompt);
        $this->assertFileExists($resolvedPath);
    }
}
