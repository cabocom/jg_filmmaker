<?php

namespace Tests\Feature;

use App\Livewire\Studio\ContextBibleDrawer;
use App\Livewire\Studio\ScriptEditor;
use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\User;
use App\Services\StoryboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StoryboardGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected Scene $scene;

    protected ScriptBlock $block;

    protected Shot $shot;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->user = User::factory()->create([
            'email' => 'filmmaker@cabocom.net',
            'name' => 'Javier Gálvez',
        ]);

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Ciberpunk Mediterráneo',
            'storyboard_style' => 'grayscale_sketch',
        ]);

        $this->scene = Scene::factory()->create([
            'project_id' => $this->project->id,
            'scene_order' => 1,
            'slugline' => 'EXT. CALLE ALMERÍA - NOCHE',
        ]);

        $this->block = ScriptBlock::factory()->create([
            'scene_id' => $this->scene->id,
            'block_order' => 1,
            'raw_text' => 'Marcus camina bajo la lluvia ácida con su gabardina negra.',
        ]);

        $this->shot = Shot::factory()->create([
            'scene_id' => $this->scene->id,
            'script_block_id' => $this->block->id,
            'shot_number' => 1,
            'shot_type' => 'Primer plano',
            'description' => 'Primer plano de Marcus mirando hacia el horizonte iluminado por neón.',
        ]);
    }

    public function test_storyboard_service_catalog_contains_the_three_required_styles(): void
    {
        $service = app(StoryboardService::class);
        $styles = $service->getStyles();

        $this->assertArrayHasKey('grayscale_sketch', $styles);
        $this->assertArrayHasKey('kids_2d_animation', $styles);
        $this->assertArrayHasKey('enki_bilal', $styles);

        $this->assertSame('Escala de Grises', $styles['grayscale_sketch']['name']);
        $this->assertSame('Animación 2D Infantil', $styles['kids_2d_animation']['name']);
        $this->assertSame('Enki Bilal', $styles['enki_bilal']['name']);
    }

    public function test_storyboard_service_builds_prompt_with_correct_modifiers_for_each_style(): void
    {
        $service = app(StoryboardService::class);

        // 1. Escala de grises
        $promptGray = $service->buildPrompt($this->shot, 'grayscale_sketch');
        $this->assertStringContainsString('charcoal and graphite drawing', $promptGray);
        $this->assertStringContainsString('Primer plano', $promptGray);

        // 2. Animación 2D Infantil
        $prompt2D = $service->buildPrompt($this->shot, 'kids_2d_animation');
        $this->assertStringContainsString("Vibrant 2D children's animated feature film still", $prompt2D);

        // 3. Enki Bilal
        $promptBilal = $service->buildPrompt($this->shot, 'enki_bilal');
        $this->assertStringContainsString('Enki Bilal', $promptBilal);
        $this->assertStringContainsString('gritty dystopian atmosphere', $promptBilal);
    }

    public function test_storyboard_service_conditions_prompt_when_character_has_reference_turnaround(): void
    {
        // Crear imagen de referencia ficticia para el personaje
        $imagePath = 'projects/'.$this->project->id.'/characters/marcus_turnaround.jpg';
        Storage::disk('public')->put($imagePath, 'fake_turnaround_bytes');

        Character::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'Marcus',
            'bio' => 'Veterano melancólico con cicatriz en la mejilla izquierda.',
            'psychology' => 'Mirada cansada y reflexiva.',
            'costume_notes' => 'Gabardina negra de cuero sintético cuello alzado.',
            'reference_image_path' => $imagePath,
        ]);

        $service = app(StoryboardService::class);
        $prompt = $service->buildPrompt($this->shot, 'enki_bilal');

        $this->assertStringContainsString("Depict character 'Marcus'", $prompt);
        $this->assertStringContainsString('Gabardina negra de cuero sintético', $prompt);
        $this->assertStringContainsString('strictly respecting the character turn-around model sheet reference', $prompt);
    }

    public function test_storyboard_service_conditions_prompt_when_scene_has_location_details(): void
    {
        $location = Location::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'CALLE ALMERÍA',
            'type' => 'EXT',
            'art_department_notes' => 'Asfalto mojado con reflejos de neones azules y cables colgantes entre edificios antiguos.',
            'lighting_notes' => 'Luz nocturna difusa por la lluvia y destellos parpadeantes de escaparates.',
        ]);

        $this->scene->update(['location_id' => $location->id]);

        $service = app(StoryboardService::class);
        $prompt = $service->buildPrompt($this->shot, 'enki_bilal');

        $this->assertStringContainsString('Location & Scenography:', $prompt);
        $this->assertStringContainsString('CALLE ALMERÍA (EXT)', $prompt);
        $this->assertStringContainsString('Asfalto mojado con reflejos de neones azules', $prompt);
        $this->assertStringContainsString('Luz nocturna difusa por la lluvia', $prompt);
    }

    public function test_storyboard_service_generates_image_from_google_ai_studio_api(): void
    {
        Config::set('services.google_ai.api_key', 'test-fake-key-google-ai');

        $fakeBase64Image = base64_encode('fake_jpeg_binary_image_data_from_google_imagen_3');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'predictions' => [
                    [
                        'bytesBase64Encoded' => $fakeBase64Image,
                        'mimeType' => 'image/jpeg',
                    ],
                ],
            ], 200),
        ]);

        $service = app(StoryboardService::class);
        $savedPath = $service->generateStoryboard($this->shot, 'enki_bilal');

        $this->assertNotNull($savedPath);
        Storage::disk('public')->assertExists($savedPath);
        $this->assertSame('fake_jpeg_binary_image_data_from_google_imagen_3', Storage::disk('public')->get($savedPath));

        $this->shot->refresh();
        $this->assertTrue($this->shot->hasStoryboard());
        $this->assertSame($savedPath, $this->shot->storyboard_image_path);
        $this->assertSame('enki_bilal', $this->shot->storyboard_style);
        $this->assertStringContainsString('Enki Bilal', (string) $this->shot->storyboard_prompt);
        $this->assertStringContainsString(asset('storage/'.$savedPath), $this->shot->storyboard_url);
    }

    public function test_script_editor_can_switch_storyboard_style_and_generate_from_livewire(): void
    {
        Config::set('services.google_ai.api_key', 'test-fake-key-google-ai');

        $fakeBase64Image = base64_encode('test_livewire_storyboard_render');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'predictions' => [
                    [
                        'bytesBase64Encoded' => $fakeBase64Image,
                        'mimeType' => 'image/jpeg',
                    ],
                ],
            ], 200),
        ]);

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->assertSet('storyboardStyle', 'grayscale_sketch')
            ->call('setStoryboardStyle', 'enki_bilal')
            ->assertSet('storyboardStyle', 'enki_bilal')
            ->call('generateShotStoryboard', $this->shot->id)
            ->assertHasNoErrors()
            ->call('openStoryboardPreview', $this->shot->id)
            ->assertSet('previewStoryboardTitle', 'Shot #1 (Primer plano)')
            ->call('closeStoryboardPreview')
            ->assertSet('previewStoryboardUrl', null);

        $this->project->refresh();
        $this->assertSame('enki_bilal', $this->project->storyboard_style);

        $this->shot->refresh();
        $this->assertTrue($this->shot->hasStoryboard());
        $this->assertSame('enki_bilal', $this->shot->storyboard_style);
    }

    public function test_context_bible_drawer_supports_character_reference_image_upload_and_delete(): void
    {
        $fakeUpload = UploadedFile::fake()->image('marcus_design_sheet.png', 800, 600);

        Livewire::actingAs($this->user)
            ->test(ContextBibleDrawer::class, ['project' => $this->project])
            ->set('newCharName', 'SARA')
            ->set('newCharBio', 'Ingeniera cibernética rebelde.')
            ->set('newCharPsychology', 'Aguda, rápida de reflejos.')
            ->set('newCharCostume', 'Mono de faena azul con chaleco táctico.')
            ->set('newCharReferenceImage', $fakeUpload)
            ->call('createCharacter')
            ->assertHasNoErrors();

        $character = Character::where('project_id', $this->project->id)->where('name', 'SARA')->firstOrFail();
        $this->assertTrue($character->hasReferenceImage());
        $this->assertNotNull($character->reference_image_path);
        Storage::disk('public')->assertExists($character->reference_image_path);
        $this->assertStringContainsString(asset('storage/'.$character->reference_image_path), $character->reference_image_url);

        // Probar eliminación de la imagen de referencia
        Livewire::actingAs($this->user)
            ->test(ContextBibleDrawer::class, ['project' => $this->project])
            ->call('deleteCharacterReferenceImage', $character->id)
            ->assertHasNoErrors();

        $character->refresh();
        $this->assertFalse($character->hasReferenceImage());
        $this->assertNull($character->reference_image_path);
    }
}
