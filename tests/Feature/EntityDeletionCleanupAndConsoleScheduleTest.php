<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use App\Services\ScriptImportService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EntityDeletionCleanupAndConsoleScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_project_deletion_cleans_both_media_and_projects_directories_on_both_disks(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $mediaFileLocal = "media/projects/{$project->id}/takes/video.mp4";
        $mediaFilePublic = "media/projects/{$project->id}/renders/rough_cut.mp4";
        $projectFileLocal = "projects/{$project->id}/storyboards/scene_1/shot_1.png";
        $projectFilePublic = "projects/{$project->id}/characters/char_1.jpg";

        Storage::disk('local')->put($mediaFileLocal, 'fake video');
        Storage::disk('public')->put($mediaFilePublic, 'fake render');
        Storage::disk('local')->put($projectFileLocal, 'fake storyboard');
        Storage::disk('public')->put($projectFilePublic, 'fake char');

        $project->delete();

        $this->assertFalse(Storage::disk('local')->exists("media/projects/{$project->id}"));
        $this->assertFalse(Storage::disk('public')->exists("media/projects/{$project->id}"));
        $this->assertFalse(Storage::disk('local')->exists("projects/{$project->id}"));
        $this->assertFalse(Storage::disk('public')->exists("projects/{$project->id}"));
    }

    public function test_scene_deletion_cleans_storyboards_directory_and_take_files(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id, 'shot_number' => 1]);

        $takeFilePath = "media/projects/{$project->id}/takes/take_1.mp4";
        Take::factory()->create(['shot_id' => $shot->id, 'file_path' => $takeFilePath]);

        $storyboardPath = "projects/{$project->id}/storyboards/scene_{$scene->id}/shot_{$shot->id}.png";

        Storage::disk('local')->put($takeFilePath, 'take content');
        Storage::disk('local')->put($storyboardPath, 'storyboard content');

        $scene->delete();

        $this->assertFalse(Storage::disk('local')->exists($takeFilePath));
        $this->assertFalse(Storage::disk('local')->exists("projects/{$project->id}/storyboards/scene_{$scene->id}"));
    }

    public function test_shot_deletion_cleans_storyboard_image_and_props_directory(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);

        $shot = Shot::factory()->create([
            'scene_id' => $scene->id,
            'shot_number' => 1,
        ]);

        $storyboardPath = "projects/{$project->id}/storyboards/scene_{$scene->id}/shot_{$shot->id}.png";
        $propsPath = "projects/{$project->id}/props/shot_{$shot->id}/prop_ref.jpg";

        $shot->update(['storyboard_image_path' => $storyboardPath]);

        Storage::disk('local')->put($storyboardPath, 'storyboard img');
        Storage::disk('local')->put($propsPath, 'prop img');

        $shot->delete();

        $this->assertFalse(Storage::disk('local')->exists($storyboardPath));
        $this->assertFalse(Storage::disk('local')->exists("projects/{$project->id}/props/shot_{$shot->id}"));
    }

    public function test_character_and_location_deletion_cleans_reference_images(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $charPath = "projects/{$project->id}/characters/hero.jpg";
        $locPath = "projects/{$project->id}/locations/tavern.jpg";

        $char = Character::factory()->create([
            'project_id' => $project->id,
            'reference_image_path' => $charPath,
        ]);

        $loc = Location::factory()->create([
            'project_id' => $project->id,
            'reference_image_path' => $locPath,
        ]);

        Storage::disk('local')->put($charPath, 'char img');
        Storage::disk('local')->put($locPath, 'loc img');

        $char->delete();
        $loc->delete();

        $this->assertFalse(Storage::disk('local')->exists($charPath));
        $this->assertFalse(Storage::disk('local')->exists($locPath));
    }

    public function test_script_import_service_populates_art_department_notes_without_description_column_error(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $service = app(ScriptImportService::class);

        $parsedData = [
            'characters' => ['DETECTIVE'],
            'locations' => ['DESPACHO'],
            'scenes' => [
                [
                    'scene_order' => 1,
                    'slugline' => 'INT. DESPACHO - NOCHE',
                    'location_name' => 'DESPACHO',
                    'time_of_day' => 'NOCHE',
                    'blocks' => [
                        [
                            'block_order' => 1,
                            'raw_text' => 'El detective revisa los informes.',
                            'screenplay_content' => 'El detective revisa los informes.',
                        ],
                    ],
                ],
            ],
        ];

        $service->importIntoProject($project, $parsedData, destructive: true);

        $this->assertDatabaseHas('locations', [
            'project_id' => $project->id,
            'name' => 'DESPACHO',
            'art_department_notes' => 'Localización importada del guion: DESPACHO',
        ]);

        $this->assertDatabaseHas('scenes', [
            'project_id' => $project->id,
            'slugline' => 'INT. DESPACHO - NOCHE',
            'time_of_day' => 'NOCHE',
        ]);
    }

    public function test_console_schedule_registers_model_prune_media_prune_and_single_tts_prune(): void
    {
        $schedule = app(Schedule::class);

        /** @var list<Event> $events */
        $events = $schedule->events();

        $commands = array_map(fn (Event $e) => $e->command, $events);

        // Filter only our artisan commands
        $modelPruneEvents = array_filter($commands, fn ($cmd) => is_string($cmd) && str_contains($cmd, 'model:prune'));
        $mediaPruneEvents = array_filter($commands, fn ($cmd) => is_string($cmd) && str_contains($cmd, 'media:prune-orphans'));
        $ttsPruneEvents = array_filter($commands, fn ($cmd) => is_string($cmd) && str_contains($cmd, 'tts:prune-orphans'));

        $this->assertCount(1, $modelPruneEvents, 'model:prune debe estar registrado una vez');
        $this->assertCount(1, $mediaPruneEvents, 'media:prune-orphans debe estar registrado una vez');
        $this->assertCount(1, $ttsPruneEvents, 'tts:prune-orphans debe estar registrado exactamente una vez (sin duplicados)');
    }

    public function test_media_prune_orphans_cleans_old_renders_and_preserves_recent_renders(): void
    {
        $oldRender = 'media/projects/1/renders/old_rough_cut.mp4';
        $recentRender = 'media/projects/1/renders/recent_rough_cut.mp4';

        Storage::disk('local')->put($oldRender, 'old render content');
        Storage::disk('local')->put($recentRender, 'recent render content');

        // Set lastModified on local fake
        touch(Storage::disk('local')->path($oldRender), Carbon::now()->subHours(3)->timestamp);
        touch(Storage::disk('local')->path($recentRender), Carbon::now()->subMinutes(10)->timestamp);

        Artisan::call('media:prune-orphans');

        $this->assertFalse(Storage::disk('local')->exists($oldRender));
        $this->assertTrue(Storage::disk('local')->exists($recentRender));
    }
}
