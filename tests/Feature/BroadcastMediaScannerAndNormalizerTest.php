<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Broadcast\NormalizeBroadcastMediaAction;
use App\Enums\BlockStage;
use App\Enums\BroadcastJobStage;
use App\Jobs\NormalizeBroadcastMediaJob;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\TvMediaNormalization;
use App\Models\User;
use App\Services\Broadcast\BroadcastProfileValidator;
use App\Services\JgAgente\HmacSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class BroadcastMediaScannerAndNormalizerTest extends TestCase
{
    use RefreshDatabase;

    private string $tempTvMediaRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempTvMediaRoot = sys_get_temp_dir().'/tv_media_test_'.uniqid('', true);
        File::ensureDirectoryExists($this->tempTvMediaRoot);
        config()->set('services.filmmaker.tv_media_root', $this->tempTvMediaRoot);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempTvMediaRoot)) {
            File::deleteDirectory($this->tempTvMediaRoot);
        }

        parent::tearDown();
    }

    public function test_media_scan_requires_valid_hmac_and_rejects_path_traversal_with_422(): void
    {
        $this->postJson('/api/v1/broadcast/media/scan', ['relative_dir' => 'ai-today/videos'])->assertStatus(401);
        $this->signedPostJson('/api/v1/broadcast/media/scan', ['relative_dir' => '../outside'])->assertStatus(422);
        $this->signedPostJson('/api/v1/broadcast/media/scan', ['relative_dir' => '/etc/passwd'])->assertStatus(422);
    }

    public function test_profile_validator_validates_720p30_by_default_and_1080p30_with_ffprobe_fake(): void
    {
        $dummyPath = $this->tempTvMediaRoot.'/sample.mp4';
        File::put($dummyPath, 'dummy media content');

        $probeJsonValid720p = (string) json_encode([
            'streams' => [
                [
                    'codec_type' => 'video',
                    'codec_name' => 'h264',
                    'pix_fmt' => 'yuv420p',
                    'width' => 1280,
                    'height' => 720,
                    'avg_frame_rate' => '30/1',
                    'r_frame_rate' => '30/1',
                    'duration' => '10.0',
                ],
                [
                    'codec_type' => 'audio',
                    'codec_name' => 'aac',
                    'sample_rate' => '48000',
                    'channels' => 2,
                    'duration' => '10.0',
                ],
            ],
            'format' => [
                'duration' => '10.0',
            ],
        ]);

        Process::fake([
            '*ffprobe*' => Process::result(output: $probeJsonValid720p),
            'ffprobe *' => Process::result(output: $probeJsonValid720p),
        ]);

        $validator = new BroadcastProfileValidator;

        $res720 = $validator->inspectMp4($dummyPath, '720p30', forceProbe: true);
        $this->assertTrue($res720['broadcast_compliant']);
        $this->assertSame('720p30', $res720['profile']);
        $this->assertSame(1280, $res720['width']);
        $this->assertSame(720, $res720['height']);
        $this->assertEmpty($res720['reasons']);

        $res1080 = $validator->inspectMp4($dummyPath, '1080p30', forceProbe: true);
        $this->assertFalse($res1080['broadcast_compliant']);
        $this->assertSame('1080p30', $res1080['profile']);
        $this->assertNotEmpty($res1080['reasons']);

        $probeJsonBadAudio = (string) json_encode([
            'streams' => [
                [
                    'codec_type' => 'video',
                    'codec_name' => 'h264',
                    'pix_fmt' => 'yuv420p',
                    'width' => 1280,
                    'height' => 720,
                    'avg_frame_rate' => '30/1',
                    'r_frame_rate' => '30/1',
                    'duration' => '10.0',
                ],
                [
                    'codec_type' => 'audio',
                    'codec_name' => 'aac',
                    'sample_rate' => '44100',
                    'channels' => 2,
                    'duration' => '10.0',
                ],
            ],
            'format' => [
                'duration' => '10.0',
            ],
        ]);

        Process::fake([
            '*ffprobe*' => Process::result(output: $probeJsonBadAudio),
            'ffprobe *' => Process::result(output: $probeJsonBadAudio),
        ]);

        $resBadAudio = $validator->inspectMp4($dummyPath, '720p30', forceProbe: true);
        $this->assertFalse($resBadAudio['broadcast_compliant']);
        $this->assertNotEmpty($resBadAudio['reasons']);

        $probeJsonBadFps = (string) json_encode([
            'streams' => [
                [
                    'codec_type' => 'video',
                    'codec_name' => 'h264',
                    'pix_fmt' => 'yuv420p',
                    'width' => 1280,
                    'height' => 720,
                    'avg_frame_rate' => '24/1',
                    'r_frame_rate' => '24/1',
                    'duration' => '10.0',
                ],
                [
                    'codec_type' => 'audio',
                    'codec_name' => 'aac',
                    'sample_rate' => '48000',
                    'channels' => 2,
                    'duration' => '10.0',
                ],
            ],
            'format' => [
                'duration' => '10.0',
            ],
        ]);

        Process::fake([
            '*ffprobe*' => Process::result(output: $probeJsonBadFps),
            'ffprobe *' => Process::result(output: $probeJsonBadFps),
        ]);

        $resBadFps = $validator->inspectMp4($dummyPath, '720p30', forceProbe: true);
        $this->assertFalse($resBadFps['broadcast_compliant']);
        $this->assertNotEmpty($resBadFps['reasons']);
    }

    public function test_media_scan_endpoint_lists_mp4_with_relative_paths_and_detects_non_compliant_files(): void
    {
        $dir = $this->tempTvMediaRoot.'/ai-today/videos';
        File::ensureDirectoryExists($dir);

        File::put($dir.'/ok.mp4', 'MOCK_VALID_MP4');
        File::put($dir.'/bad.mp4', 'MOCK_INVALID_MP4');

        $response = $this->signedPostJson('/api/v1/broadcast/media/scan', [
            'relative_dir' => 'ai-today/videos',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('profile', '720p30');
        $response->assertJsonPath('total', 2);
        $response->assertJsonPath('compliant_count', 1);
        $response->assertJsonPath('non_compliant_count', 1);

        $files = $response->json('files');
        $this->assertCount(2, $files);

        foreach ($files as $file) {
            $this->assertStringStartsNotWith('/', $file['relative_path']);
        }

        $this->assertSame('ai-today/videos/bad.mp4', $files[0]['relative_path']);
        $this->assertFalse($files[0]['broadcast_compliant']);

        $this->assertSame('ai-today/videos/ok.mp4', $files[1]['relative_path']);
        $this->assertTrue($files[1]['broadcast_compliant']);
    }

    public function test_media_normalize_enqueues_job_blocks_concurrent_duplicate_with_409_and_replaces_file_atomically(): void
    {
        $dir = $this->tempTvMediaRoot.'/ai-today/videos';
        File::ensureDirectoryExists($dir);
        $badFilePath = $dir.'/bad.mp4';
        File::put($badFilePath, 'MOCK_INVALID_MP4');

        Queue::fake();

        $response = $this->signedPostJson('/api/v1/broadcast/media/normalize', [
            'relative_path' => 'ai-today/videos/bad.mp4',
        ]);

        $response->assertStatus(202);
        $jobId = (string) $response->json('job_id');
        $this->assertNotEmpty($jobId);

        Queue::assertPushed(NormalizeBroadcastMediaJob::class, fn (NormalizeBroadcastMediaJob $job): bool => $job->normalizationId === $jobId);

        // Immediate duplicate request on same relative_path must return 409
        $conflictResponse = $this->signedPostJson('/api/v1/broadcast/media/normalize', [
            'relative_path' => 'ai-today/videos/bad.mp4',
        ]);
        $conflictResponse->assertStatus(409);

        // Execute job synchronously
        $action = app(NormalizeBroadcastMediaAction::class);
        $jobInstance = new NormalizeBroadcastMediaJob($jobId);
        $jobInstance->handle($action);

        // Normalization record in database is ready
        $norm = TvMediaNormalization::find($jobId);
        $this->assertNotNull($norm);
        $this->assertSame('ready', $norm->stage);

        // No lingering .normalizing. temporary files
        $tmpFiles = glob($this->tempTvMediaRoot.'/ai-today/videos/*.normalizing.*');
        $this->assertEmpty($tmpFiles);

        // GET status endpoint returns 200 with stage ready
        $statusResponse = $this->signedGetJson("/api/v1/broadcast/media/normalize/{$jobId}");
        $statusResponse->assertStatus(200);
        $statusResponse->assertJsonPath('stage', 'ready');
        $statusResponse->assertJsonPath('job_id', $jobId);
    }

    public function test_broadcast_job_with_720p30_and_output_relative_path_exports_episode_into_tv_media_root(): void
    {
        Storage::fake('media');
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'title' => 'T',
            'status' => 'in_progress',
            'storyboard_style' => 'static_card',
        ]);
        $scene = Scene::create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. ESTUDIO - DIA',
            'time_of_day' => 'DIA',
        ]);
        $block = ScriptBlock::create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'stage' => BlockStage::SHOTS->value,
            'raw_text' => 'Texto',
        ]);
        $shot = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'WIDE',
            'description' => 'Plano 1',
        ]);
        $job = BroadcastJob::create([
            'channel_id' => 'ai-today',
            'format' => 'pill',
            'title' => 'Test 720p',
            'raw_text' => 'Texto',
            'language' => 'es',
            'sources' => ['Wikipedia'],
            'visual_mode' => 'static_card',
            'stage' => BroadcastJobStage::SHOTS,
            'project_id' => $project->id,
            'script_block_id' => $block->id,
            'artifacts' => [
                'resolution_profile' => '720p30',
                'output_relative_path' => 'ai-today/videos/episodio-720p.mp4',
                'shots' => [
                    [
                        'shot_id' => (string) $shot->id,
                        'shot_number' => 1,
                        'oral_text' => 'Hola mundo broadcast.',
                        'estimated_seconds' => 15.0,
                    ],
                ],
                'shots_count' => 1,
            ],
        ]);

        $this->signedPostJson("/api/v1/broadcast/jobs/{$job->id}/render", [])->assertStatus(202);

        $expectedExportPath = $this->tempTvMediaRoot.'/ai-today/videos/episodio-720p.mp4';
        $this->assertFileExists($expectedExportPath);

        $refreshedJob = $job->fresh();
        $this->assertSame(
            'ai-today/videos/episodio-720p.mp4',
            $refreshedJob->artifacts['exports']['tv_drive_relative_path']
        );
    }

    private function signedPostJson(string $path, array $payload): TestResponse
    {
        $secret = 'test-broadcast-secret-1234567890';
        config()->set('services.jg_agente.secret', $secret);
        $rawBody = (string) json_encode($payload);
        $headers = (new HmacSigner)->generateSignatureHeaders(
            method: 'POST',
            path: $path,
            rawBody: $rawBody,
            secret: $secret
        );

        return $this->postJson($path, $payload, $headers);
    }

    private function signedGetJson(string $path): TestResponse
    {
        $secret = 'test-broadcast-secret-1234567890';
        config()->set('services.jg_agente.secret', $secret);
        $headers = (new HmacSigner)->generateSignatureHeaders(
            method: 'GET',
            path: $path,
            rawBody: '',
            secret: $secret
        );

        return $this->getJson($path, $headers);
    }
}
