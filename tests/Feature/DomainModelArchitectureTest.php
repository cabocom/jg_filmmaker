<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainModelArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_domain_entities_generate_uuidv4_primary_keys(): void
    {
        $user = User::factory()->create();
        $this->assertTrue(Str::isUuid($user->id));

        $project = Project::factory()->create(['user_id' => $user->id]);
        $this->assertTrue(Str::isUuid($project->id));

        $character = Character::factory()->create(['project_id' => $project->id]);
        $this->assertTrue(Str::isUuid($character->id));

        $location = Location::factory()->create(['project_id' => $project->id]);
        $this->assertTrue(Str::isUuid($location->id));

        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'location_id' => $location->id,
        ]);
        $this->assertTrue(Str::isUuid($scene->id));

        $block = ScriptBlock::factory()->create(['scene_id' => $scene->id]);
        $this->assertTrue(Str::isUuid($block->id));

        $shot = Shot::factory()->create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
        ]);
        $this->assertTrue(Str::isUuid($shot->id));

        $take = Take::factory()->create(['shot_id' => $shot->id]);
        $this->assertTrue(Str::isUuid($take->id));
    }

    public function test_domain_relationships_and_navigation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create(['project_id' => $project->id]);
        $location = Location::factory()->create(['project_id' => $project->id]);
        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'location_id' => $location->id,
        ]);
        $block = ScriptBlock::factory()->create(['scene_id' => $scene->id]);
        $shot = Shot::factory()->create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
        ]);
        $take = Take::factory()->create(['shot_id' => $shot->id]);

        // Assert relationships
        $this->assertTrue($user->projects->contains($project));
        $this->assertEquals($user->id, $project->user->id);

        $this->assertTrue($project->characters->contains($character));
        $this->assertEquals($project->id, $character->project->id);

        $this->assertTrue($project->locations->contains($location));
        $this->assertEquals($project->id, $location->project->id);

        $this->assertTrue($project->scenes->contains($scene));
        $this->assertEquals($project->id, $scene->project->id);
        $this->assertEquals($location->id, $scene->location->id);
        $this->assertTrue($location->scenes->contains($scene));

        $this->assertTrue($scene->scriptBlocks->contains($block));
        $this->assertEquals($scene->id, $block->scene->id);

        $this->assertTrue($scene->shots->contains($shot));
        $this->assertEquals($scene->id, $shot->scene->id);
        $this->assertEquals($block->id, $shot->scriptBlock->id);

        $this->assertTrue($shot->takes->contains($take));
        $this->assertEquals($shot->id, $take->shot->id);
    }

    public function test_user_locks_and_technical_meta_json_casts(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $locksData = [
            'locked_fields' => ['dialogue_speaker'],
            'manual_diffs' => [
                ['stage' => 'structured', 'original_ai' => 'A', 'user_override' => 'B'],
            ],
            'pinned_terms' => ['Javi' => 'Javier'],
        ];

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'user_locks' => $locksData,
            'stage' => 'screenplay',
        ]);

        $freshBlock = ScriptBlock::findOrFail($block->id);
        $this->assertIsArray($freshBlock->user_locks);
        $this->assertEquals(['dialogue_speaker'], $freshBlock->user_locks['locked_fields']);
        $this->assertEquals('Javier', $freshBlock->user_locks['pinned_terms']['Javi']);

        $shot = Shot::factory()->create(['scene_id' => $scene->id]);
        $metaData = [
            'duration_seconds' => 12.5,
            'resolution' => '3840x2160',
            'codec' => 'prores422hq',
            'fps' => 24.0,
        ];

        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'technical_meta' => $metaData,
            'is_good_take' => true,
        ]);

        $freshTake = Take::findOrFail($take->id);
        $this->assertIsArray($freshTake->technical_meta);
        $this->assertEquals(12.5, $freshTake->technical_meta['duration_seconds']);
        $this->assertEquals('prores422hq', $freshTake->technical_meta['codec']);
        $this->assertTrue($freshTake->is_good_take);
    }

    public function test_cascading_deletes_when_project_is_removed(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create(['project_id' => $project->id]);
        $location = Location::factory()->create(['project_id' => $project->id]);
        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'location_id' => $location->id,
        ]);
        $block = ScriptBlock::factory()->create(['scene_id' => $scene->id]);
        $shot = Shot::factory()->create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
        ]);
        $take = Take::factory()->create(['shot_id' => $shot->id]);

        $project->delete();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('characters', ['id' => $character->id]);
        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
        $this->assertDatabaseMissing('scenes', ['id' => $scene->id]);
        $this->assertDatabaseMissing('script_blocks', ['id' => $block->id]);
        $this->assertDatabaseMissing('shots', ['id' => $shot->id]);
        $this->assertDatabaseMissing('takes', ['id' => $take->id]);
    }
}
