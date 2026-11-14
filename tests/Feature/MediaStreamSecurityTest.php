<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStreamSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $attacker;

    protected User $admin;

    protected Project $project;

    protected Scene $scene;

    protected Shot $shot;

    protected Character $character;

    protected Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->owner = User::factory()->create();
        $this->attacker = User::factory()->create();
        $this->admin = User::factory()->create(['is_admin' => true]);

        $this->project = Project::factory()->create(['user_id' => $this->owner->id]);
        $this->scene = Scene::factory()->create(['project_id' => $this->project->id]);

        // Guardar storyboard en local
        $storyboardPath = "media/projects/{$this->project->id}/storyboards/scene_{$this->scene->id}/shot_test.png";
        Storage::disk('local')->put($storyboardPath, 'fake_storyboard_png_data');
        $this->shot = Shot::factory()->create([
            'scene_id' => $this->scene->id,
            'storyboard_image_path' => $storyboardPath,
        ]);

        // Guardar personaje en local
        $charPath = "media/projects/{$this->project->id}/characters/char_test.png";
        Storage::disk('local')->put($charPath, 'fake_char_png_data');
        $this->character = Character::factory()->create([
            'project_id' => $this->project->id,
            'reference_image_path' => $charPath,
        ]);

        // Guardar localización en local
        $locPath = "media/projects/{$this->project->id}/locations/loc_test.jpg";
        Storage::disk('local')->put($locPath, 'fake_loc_jpg_data');
        $this->location = Location::factory()->create([
            'project_id' => $this->project->id,
            'reference_image_path' => $locPath,
        ]);
    }

    public function test_guest_is_redirected_to_login_when_accessing_media_routes(): void
    {
        $this->get(route('media.storyboard', ['shot' => $this->shot->id]))
            ->assertRedirect(route('login'));

        $this->get(route('media.character', ['character' => $this->character->id]))
            ->assertRedirect(route('login'));

        $this->get(route('media.location', ['location' => $this->location->id]))
            ->assertRedirect(route('login'));
    }

    public function test_owner_can_stream_storyboard_character_and_location_with_private_cache_headers(): void
    {
        $resStoryboard = $this->actingAs($this->owner)
            ->get(route('media.storyboard', ['shot' => $this->shot->id]));
        $resStoryboard->assertOk();
        $resStoryboard->assertHeader('Cache-Control', 'max-age=3600, private');

        $resChar = $this->actingAs($this->owner)
            ->get(route('media.character', ['character' => $this->character->id]));
        $resChar->assertOk();
        $resChar->assertHeader('Cache-Control', 'max-age=3600, private');

        $resLoc = $this->actingAs($this->owner)
            ->get(route('media.location', ['location' => $this->location->id]));
        $resLoc->assertOk();
        $resLoc->assertHeader('Cache-Control', 'max-age=3600, private');
    }

    public function test_attacker_cannot_stream_other_tenant_storyboard_character_or_location(): void
    {
        $this->actingAs($this->attacker)
            ->get(route('media.storyboard', ['shot' => $this->shot->id]))
            ->assertForbidden();

        $this->actingAs($this->attacker)
            ->get(route('media.character', ['character' => $this->character->id]))
            ->assertForbidden();

        $this->actingAs($this->attacker)
            ->get(route('media.location', ['location' => $this->location->id]))
            ->assertForbidden();
    }

    public function test_admin_can_stream_any_project_media(): void
    {
        $this->actingAs($this->admin)
            ->get(route('media.storyboard', ['shot' => $this->shot->id]))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('media.character', ['character' => $this->character->id]))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('media.location', ['location' => $this->location->id]))
            ->assertOk();
    }

    public function test_legacy_files_on_public_disk_fallback_successfully(): void
    {
        $legacyPath = "projects/{$this->project->id}/storyboards/legacy_shot.png";
        Storage::disk('public')->put($legacyPath, 'legacy_storyboard_data');

        $legacyShot = Shot::factory()->create([
            'scene_id' => $this->scene->id,
            'storyboard_image_path' => $legacyPath,
        ]);

        $this->actingAs($this->owner)
            ->get(route('media.storyboard', ['shot' => $legacyShot->id]))
            ->assertOk();
    }
}
