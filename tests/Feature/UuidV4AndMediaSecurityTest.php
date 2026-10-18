<?php

namespace Tests\Feature;

use App\Models\AgentRequestLog;
use App\Models\Character;
use App\Models\Location;
use App\Models\OneTimePassword;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\SecurityBreachLog;
use App\Models\Shot;
use App\Models\Take;
use App\Models\TokenPricingRate;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class UuidV4AndMediaSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_all_models_generate_strict_rfc4122_uuid_v4(): void
    {
        $user = User::factory()->create();
        $this->assertSame(4, Uuid::fromString($user->id)->getVersion());

        $project = Project::factory()->create(['user_id' => $user->id]);
        $this->assertSame(4, Uuid::fromString($project->id)->getVersion());

        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $this->assertSame(4, Uuid::fromString($scene->id)->getVersion());

        $block = ScriptBlock::factory()->create(['scene_id' => $scene->id]);
        $this->assertSame(4, Uuid::fromString($block->id)->getVersion());

        $character = Character::factory()->create(['project_id' => $project->id]);
        $this->assertSame(4, Uuid::fromString($character->id)->getVersion());

        $location = Location::factory()->create(['project_id' => $project->id]);
        $this->assertSame(4, Uuid::fromString($location->id)->getVersion());

        $shot = Shot::factory()->create(['scene_id' => $scene->id]);
        $this->assertSame(4, Uuid::fromString($shot->id)->getVersion());

        $take = Take::factory()->create(['shot_id' => $shot->id]);
        $this->assertSame(4, Uuid::fromString($take->id)->getVersion());

        $otp = OneTimePassword::create([
            'user_id' => $user->id,
            'code_hash' => hash('sha256', '123456'),
            'expires_at' => now()->addMinutes(10),
        ]);
        $this->assertSame(4, Uuid::fromString($otp->id)->getVersion());

        $rate = TokenPricingRate::create([
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'input_cost_per_million' => 2.50,
            'output_cost_per_million' => 10.00,
        ]);
        $this->assertSame(4, Uuid::fromString($rate->id)->getVersion());

        $securityLog = SecurityBreachLog::create([
            'ip_address' => '127.0.0.1',
            'user_id' => $user->id,
            'attempted_at' => now(),
            'reason' => 'test_reason',
        ]);
        $this->assertSame(4, Uuid::fromString($securityLog->id)->getVersion());

        $userLog = UserActivityLog::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'action' => 'test_action',
            'entity_type' => 'project',
            'entity_id' => $project->id,
            'description' => 'Test description',
        ]);
        $this->assertSame(4, Uuid::fromString($userLog->id)->getVersion());

        $agentLog = AgentRequestLog::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'tool_name' => 'test_tool',
            'input_tokens' => 10,
            'output_tokens' => 10,
            'total_tokens' => 20,
            'estimated_cost_usd' => 0.001,
        ]);
        $this->assertSame(4, Uuid::fromString($agentLog->id)->getVersion());
    }

    public function test_guest_is_redirected_to_login_when_accessing_take_media_stream(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);
        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'file_path' => "media/projects/{$project->id}/scenes/{$scene->id}/takes/take.mp4",
        ]);

        $response = $this->get(route('media.take', ['take' => $take->id]));
        $response->assertRedirect(route('login'));
    }

    public function test_non_owner_user_is_forbidden_from_streaming_take_media(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $project = Project::factory()->create(['user_id' => $owner->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);
        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'file_path' => "media/projects/{$project->id}/scenes/{$scene->id}/takes/take.mp4",
        ]);

        Storage::disk('local')->put($take->file_path, 'secret-footage');

        $response = $this->actingAs($attacker)->get(route('media.take', ['take' => $take->id]));
        $response->assertForbidden();
    }

    public function test_owner_can_successfully_stream_take_media(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);
        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'file_path' => "media/projects/{$project->id}/scenes/{$scene->id}/takes/take.mp4",
        ]);

        Storage::disk('local')->put($take->file_path, 'secret-footage-content');

        $response = $this->actingAs($owner)->get(route('media.take', ['take' => $take->id]));
        $response->assertOk();
    }

    public function test_owner_can_stream_render_and_non_owner_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $renderFile = 'rough_cut_test.mp4';
        $relativePath = "media/projects/{$project->id}/renders/{$renderFile}";
        Storage::disk('local')->put($relativePath, 'rough-cut-video');

        // Intento anónimo
        $this->get(route('media.render', ['project' => $project->id, 'filename' => $renderFile]))
            ->assertRedirect(route('login'));

        // Intento de atacante
        $this->actingAs($attacker)
            ->get(route('media.render', ['project' => $project->id, 'filename' => $renderFile]))
            ->assertForbidden();

        // Acceso legítimo del propietario
        $this->actingAs($owner)
            ->get(route('media.render', ['project' => $project->id, 'filename' => $renderFile]))
            ->assertOk();
    }
}
