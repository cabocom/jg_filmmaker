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

    public function test_context_bible_drawer_supports_location_reference_image_upload_and_delete(): void
    {
        $fakeUpload = UploadedFile::fake()->image('pasillo_entrada.jpg', 1280, 720);

        Livewire::actingAs($this->user)
            ->test(ContextBibleDrawer::class, ['project' => $this->project])
            ->set('newLocName', 'CASA ENTRADA/PASILLO')
            ->set('newLocType', 'INT')
            ->set('newLocArtNotes', 'Paredes con gotelé desgastado.')
            ->set('newLocLightingNotes', 'Luz fluorescente parpadeante.')
            ->set('newLocReferenceImage', $fakeUpload)
            ->call('createLocation')
            ->assertHasNoErrors();

        $location = Location::where('project_id', $this->project->id)->where('name', 'CASA ENTRADA/PASILLO')->firstOrFail();
        $this->assertTrue($location->hasReferenceImage());
        $this->assertNotNull($location->reference_image_path);
        Storage::disk('public')->assertExists($location->reference_image_path);
        $this->assertStringContainsString(asset('storage/'.$location->reference_image_path), $location->reference_image_url);

        // Probar eliminación de la imagen de referencia de localización
        Livewire::actingAs($this->user)
            ->test(ContextBibleDrawer::class, ['project' => $this->project])
            ->call('deleteLocationReferenceImage', $location->id)
            ->assertHasNoErrors();

        $location->refresh();
        $this->assertFalse($location->hasReferenceImage());
        $this->assertNull($location->reference_image_path);
    }

    public function test_script_editor_storyboard_modal_opens_with_prefilled_prompt_and_allows_customization(): void
    {
        Config::set('services.google_ai.api_key', 'test-fake-key-google-ai');

        $fakeBase64Image = base64_encode('test_custom_prompt_rendered_image');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Storyboard generated successfully.'],
                                [
                                    'inlineData' => [
                                        'mimeType' => 'image/png',
                                        'data' => $fakeBase64Image,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $customPrompt = 'Primer plano dramático de Marcus sosteniendo un amuleto alienígena con luz cian.';

        $lw = Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->assertSet('showStoryboardModal', false)
            ->call('openStoryboardModal', $this->shot->id)
            ->assertSet('showStoryboardModal', true)
            ->assertSet('storyboardShotId', $this->shot->id);

        $this->assertNotEmpty($lw->get('storyboardModalPrompt'));

        $lw->set('storyboardModalPrompt', $customPrompt)
            ->call('confirmGenerateStoryboard')
            ->assertHasNoErrors()
            ->assertSet('showStoryboardModal', false);

        $this->shot->refresh();
        $this->assertTrue($this->shot->hasStoryboard());
        $this->assertSame($customPrompt, $this->shot->storyboard_prompt);
    }

    public function test_script_editor_storyboard_modal_manages_props_and_limits_to_three(): void
    {
        Config::set('services.google_ai.api_key', 'test-fake-key-google-ai');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'image/png',
                                        'data' => base64_encode('fake_img'),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $prop1 = UploadedFile::fake()->image('ambientador.jpg', 400, 400);
        $prop2 = UploadedFile::fake()->image('movil.jpg', 400, 400);
        $prop3 = UploadedFile::fake()->image('llaves.jpg', 400, 400);
        $prop4 = UploadedFile::fake()->image('reloj.jpg', 400, 400);

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->call('openStoryboardModal', $this->shot->id)
            ->set('storyboardPropUploads', [$prop1, $prop2, $prop3, $prop4])
            // Debe recortar a 3 automáticamente
            ->assertCount('storyboardPropUploads', 3)
            // Eliminar el segundo prop
            ->call('removeStoryboardProp', 1)
            ->assertCount('storyboardPropUploads', 2)
            ->call('confirmGenerateStoryboard')
            ->assertHasNoErrors();
    }

    public function test_storyboard_service_multimodal_sends_character_location_and_props_to_gemini(): void
    {
        Config::set('services.google_ai.api_key', 'test-fake-key-google-ai');
        Config::set('services.google_ai.model', 'gemini-2.5-flash-image');

        // Crear personaje con imagen
        $charImgPath = 'projects/'.$this->project->id.'/characters/char.png';
        Storage::disk('public')->put($charImgPath, 'character_dummy_png_bytes');
        $character = Character::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'EDURNE',
            'reference_image_path' => $charImgPath,
        ]);

        // Crear localización con imagen
        $locImgPath = 'projects/'.$this->project->id.'/locations/loc.jpg';
        Storage::disk('public')->put($locImgPath, 'location_dummy_jpg_bytes');
        $location = Location::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'BAÑO',
            'reference_image_path' => $locImgPath,
        ]);

        $this->scene->update(['location_id' => $location->id]);

        $prop1 = UploadedFile::fake()->image('prop_ambientador.png', 200, 200);

        $capturedPayload = null;
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => function ($request) use (&$capturedPayload) {
                $capturedPayload = $request->data();

                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'inlineData' => [
                                            'mimeType' => 'image/png',
                                            'data' => base64_encode('rendered_gemini_image'),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            },
        ]);

        $service = app(StoryboardService::class);
        $savedPath = $service->generateStoryboard(
            shot: $this->shot,
            style: 'grayscale_sketch',
            customPrompt: 'Edurne en el baño sosteniendo el ambientador.',
            characterId: $character->id,
            locationId: $location->id,
            propImages: [$prop1]
        );

        $this->assertNotNull($savedPath);
        $this->assertNotNull($capturedPayload);

        // Validar que se enviaron los parts multimodales a Google AI Studio
        $parts = $capturedPayload['contents'][0]['parts'] ?? [];
        $this->assertCount(4, $parts); // 1 char image + 1 loc image + 1 prop image + 1 text prompt

        // Verificar que hay 3 inline_data y 1 text
        $inlineDataCount = 0;
        $textContent = null;
        foreach ($parts as $part) {
            if (isset($part['inline_data'])) {
                $inlineDataCount++;
            }
            if (isset($part['text'])) {
                $textContent = $part['text'];
            }
        }

        $this->assertSame(3, $inlineDataCount);
        $this->assertSame('Edurne en el baño sosteniendo el ambientador.', $textContent);
    }
}
