<?php

namespace Tests\Feature;

use App\Livewire\Studio\ScriptEditor;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TakeMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_take_video_can_be_uploaded_and_stored_in_uuid_partitioned_path(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $block = ScriptBlock::factory()->create(['scene_id' => $scene->id]);
        $shot = Shot::factory()->create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
        ]);
        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'take_number' => 1,
            'file_path' => null,
        ]);

        $fakeVideo = UploadedFile::fake()->create('take1.mp4', 5000, 'video/mp4');

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->set('uploadingTakeId', $take->id)
            ->set('videoUpload', $fakeVideo)
            ->call('uploadTakeVideo');

        $freshTake = $take->fresh();
        $expectedPath = "media/projects/{$project->id}/scenes/{$scene->id}/takes/{$take->id}.mp4";

        $this->assertSame($expectedPath, $freshTake->file_path);
        $this->assertNotNull($freshTake->video_url);
        Storage::disk('local')->assertExists($expectedPath);
    }

    public function test_deleting_take_removes_physical_video_file_from_storage(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);
        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'take_number' => 1,
        ]);

        $storagePath = $take->generateStoragePath('mp4');
        Storage::disk('local')->put($storagePath, 'fake-mp4-binary-content');
        $take->update(['file_path' => $storagePath]);

        Storage::disk('local')->assertExists($storagePath);

        // Eliminar a través del método Livewire
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('deleteTake', $take->id);

        $this->assertDatabaseMissing('takes', ['id' => $take->id]);
        Storage::disk('local')->assertMissing($storagePath);
    }

    public function test_replacing_video_deletes_previous_physical_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);
        $take = Take::factory()->create(['shot_id' => $shot->id, 'take_number' => 1]);

        $oldPath = "media/projects/{$project->id}/scenes/{$scene->id}/takes/old_{$take->id}.mp4";
        Storage::disk('local')->put($oldPath, 'old-video');
        $take->update(['file_path' => $oldPath]);

        Storage::disk('local')->assertExists($oldPath);

        $newVideo = UploadedFile::fake()->create('take_new.mov', 4000, 'video/quicktime');

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->set('uploadingTakeId', $take->id)
            ->set('videoUpload', $newVideo)
            ->call('uploadTakeVideo');

        // El archivo anterior debe haberse eliminado
        Storage::disk('local')->assertMissing($oldPath);

        // El nuevo debe existir en la ruta con extensión mov
        $newExpectedPath = "media/projects/{$project->id}/scenes/{$scene->id}/takes/{$take->id}.mov";
        Storage::disk('local')->assertExists($newExpectedPath);
        $this->assertSame($newExpectedPath, $take->fresh()->file_path);
    }

    public function test_generate_storage_path_rejects_unauthorized_extensions(): void
    {
        $take = Take::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Extensión de archivo de vídeo no permitida: php');

        $take->generateStoragePath('php');
    }

    public function test_upload_take_video_rejects_dangerous_extensions_and_unauthorized_mimes(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $block = ScriptBlock::factory()->create(['scene_id' => $scene->id]);
        $shot = Shot::factory()->create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
        ]);
        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'take_number' => 1,
            'file_path' => null,
        ]);

        // Intento de subir un archivo con extensión .php pero con MIME de vídeo
        $maliciousPhp = UploadedFile::fake()->create('shell.php', 100, 'video/mp4');

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->set('uploadingTakeId', $take->id)
            ->set('videoUpload', $maliciousPhp)
            ->call('uploadTakeVideo')
            ->assertHasErrors(['videoUpload']);

        $this->assertNull($take->fresh()->file_path);
    }
}
