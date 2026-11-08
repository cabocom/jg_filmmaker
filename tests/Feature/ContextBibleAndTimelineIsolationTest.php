<?php

namespace Tests\Feature;

use App\Livewire\Studio\ContextBibleDrawer;
use App\Livewire\Studio\RoughCutTimeline;
use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ContextBibleAndTimelineIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_edit_characters_of_another_project(): void
    {
        $userA = User::factory()->create();
        $projectA = Project::factory()->create(['user_id' => $userA->id]);

        $userB = User::factory()->create();
        $projectB = Project::factory()->create(['user_id' => $userB->id]);
        $charB = Character::factory()->create([
            'project_id' => $projectB->id,
            'name' => 'Personaje Secreto B',
            'bio' => 'Biografia confidencial',
        ]);

        Livewire::actingAs($userA)
            ->test(ContextBibleDrawer::class, ['project' => $projectA])
            ->call('startEditCharacter', $charB->id)
            ->assertStatus(404);
    }

    public function test_cannot_edit_locations_of_another_project(): void
    {
        $userA = User::factory()->create();
        $projectA = Project::factory()->create(['user_id' => $userA->id]);

        $userB = User::factory()->create();
        $projectB = Project::factory()->create(['user_id' => $userB->id]);
        $locB = Location::factory()->create([
            'project_id' => $projectB->id,
            'name' => 'Localizacion Secreta B',
        ]);

        Livewire::actingAs($userA)
            ->test(ContextBibleDrawer::class, ['project' => $projectA])
            ->call('startEditLocation', $locB->id)
            ->assertStatus(404);
    }

    public function test_rough_cut_timeline_rejects_cross_tenant_take_playback(): void
    {
        $userA = User::factory()->create();
        $projectA = Project::factory()->create(['user_id' => $userA->id]);

        $userB = User::factory()->create();
        $projectB = Project::factory()->create(['user_id' => $userB->id]);
        $sceneB = Scene::factory()->create(['project_id' => $projectB->id]);
        $shotB = Shot::factory()->create(['scene_id' => $sceneB->id]);
        $takeB = Take::factory()->create([
            'shot_id' => $shotB->id,
            'is_good_take' => true,
            'file_path' => 'media/projects/secret/b.mp4',
        ]);

        Livewire::actingAs($userA)
            ->test(RoughCutTimeline::class, ['project' => $projectA])
            ->call('selectTakeForPlayback', $takeB->id)
            ->assertStatus(404);
    }

    public function test_rough_cut_timeline_does_not_leak_other_project_take_in_render(): void
    {
        $userA = User::factory()->create();
        $projectA = Project::factory()->create(['user_id' => $userA->id]);

        $userB = User::factory()->create();
        $projectB = Project::factory()->create(['user_id' => $userB->id]);
        $sceneB = Scene::factory()->create(['project_id' => $projectB->id]);
        $shotB = Shot::factory()->create(['scene_id' => $sceneB->id]);
        $takeB = Take::factory()->create([
            'shot_id' => $shotB->id,
            'is_good_take' => true,
            'file_path' => 'media/projects/secret/b.mp4',
            'editorial_notes' => 'Notas privadas B',
        ]);

        $component = Livewire::actingAs($userA)
            ->test(RoughCutTimeline::class, ['project' => $projectA]);

        // Manipular propiedad pública con el ID de la toma ajena
        $component->set('currentPlayingTakeId', $takeB->id);

        // La vista no debe renderizar las notas editoriales ni los datos de la toma ajena
        $component->assertDontSee('Notas privadas B');
        $this->assertNull($component->viewData('currentTake'));
    }

    public function test_owner_can_edit_character_with_voice_and_reference_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create([
            'project_id' => $project->id,
            'name' => 'John Connor',
            'bio' => 'Líder de la resistencia humana.',
        ]);

        $pngBytes = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $fakeImage = UploadedFile::fake()->createWithContent('connor.png', $pngBytes);

        Livewire::actingAs($user)
            ->test(ContextBibleDrawer::class, ['project' => $project])
            ->set('isOpen', true)
            ->call('startEditCharacter', $character->id)
            ->assertSee('Probar voz')
            ->assertSee('Arrastra y suelta')
            ->set('editingCharVoiceId', 'carlfm')
            ->set('editingCharBio', 'Líder legendario de la resistencia humana.')
            ->set('editingCharReferenceImage', $fakeImage)
            ->call('saveCharacter')
            ->assertHasNoErrors();

        $character->refresh();
        $this->assertSame('carlfm', $character->custom_attributes['voice_id'] ?? null);
        $this->assertSame('Líder legendario de la resistencia humana.', $character->bio);
        $this->assertNotNull($character->reference_image_path);
        Storage::disk('public')->assertExists($character->reference_image_path);
    }

    public function test_character_importance_and_costume_notes_save_and_display_correctly(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create([
            'project_id' => $project->id,
            'name' => 'Sara Connor',
            'costume_notes' => 'Chaqueta de cuero militar negra.',
            'psychology' => 'Interviene activamente en el desarrollo de la escena.',
        ]);

        Livewire::actingAs($user)
            ->test(ContextBibleDrawer::class, ['project' => $project])
            ->set('isOpen', true)
            ->assertDontSee('Interviene activamente en el desarrollo de la escena.')
            ->assertSee('Sara Connor')
            ->call('startEditCharacter', $character->id)
            ->set('editingCharImportance', 'protagonista')
            ->set('editingCharCostume', "Gabardina desgastada\nBotas de combate tácticas")
            ->call('saveCharacter')
            ->assertHasNoErrors();

        $character->refresh();
        $this->assertSame('protagonista', $character->custom_attributes['importance'] ?? null);
        $this->assertSame("Gabardina desgastada\nBotas de combate tácticas", $character->costume_notes);
    }
}
