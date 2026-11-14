<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Scene;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use App\Services\Media\MediaStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class MediaStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_storage_service_basic_operations(): void
    {
        Storage::fake('local');
        $service = new MediaStorageService;

        $path = 'media/projects/demo/test.txt';
        $this->assertFalse($service->exists($path));

        $service->put($path, 'CONTENIDO_PRUEBA');
        $this->assertTrue($service->exists($path));
        $this->assertSame('CONTENIDO_PRUEBA', $service->get($path));

        $service->delete($path);
        $this->assertFalse($service->exists($path));
    }

    public function test_media_storage_service_serves_local_file_safely(): void
    {
        Storage::fake('local');
        $service = new MediaStorageService;

        $path = 'media/projects/demo/poster.jpg';
        $service->put($path, 'BINARY_IMAGE_DATA');

        $response = $service->serve($path);
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', (string) $response->headers->get('Cache-Control'));
    }

    public function test_media_storage_service_supports_x_accel_redirect_when_enabled(): void
    {
        Storage::fake('local');
        config(['filesystems.use_x_accel' => true]);

        $service = new MediaStorageService;
        $path = 'media/projects/demo/video.mp4';
        Storage::disk('local')->put($path, 'VIDEO_BYTES');

        $response = $service->serve($path);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/protected-media/media/projects/demo/video.mp4', $response->headers->get('X-Accel-Redirect'));
    }

    public function test_media_stream_controller_uses_media_storage_service_to_stream_take(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);

        $filePath = "media/projects/{$project->id}/takes/take_01.mp4";
        Storage::disk('local')->put($filePath, 'VIDEO_PAYLOAD');

        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'file_path' => $filePath,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('media.take', ['take' => $take->id]));
        $response->assertStatus(200);
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', (string) $response->headers->get('Cache-Control'));
    }

    public function test_media_storage_service_redirects_when_presigned_url_is_generated(): void
    {
        $mockStorage = $this->createMock(MediaStorageService::class);
        $mockStorage->expects($this->once())
            ->method('serve')
            ->willReturn(new RedirectResponse('https://s3.amazonaws.com/media-bucket/signed-video.mp4?signature=abc'));

        $this->app->instance(MediaStorageService::class, $mockStorage);

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);
        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'file_path' => "media/projects/{$project->id}/takes/take_s3.mp4",
        ]);

        $this->actingAs($user);

        $response = $this->get(route('media.take', ['take' => $take->id]));
        $response->assertStatus(302);
        $response->assertRedirect('https://s3.amazonaws.com/media-bucket/signed-video.mp4?signature=abc');
    }
}
