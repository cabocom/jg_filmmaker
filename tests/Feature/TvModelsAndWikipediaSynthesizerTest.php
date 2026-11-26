<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BroadcastJobStage;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\TvAdSlot;
use App\Models\TvChannelConfig;
use App\Models\TvDaySchedule;
use App\Models\TvRundown;
use App\Models\TvVideo;
use App\Models\User;
use App\Services\Tv\WikipediaRundownSynthesizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class TvModelsAndWikipediaSynthesizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_tv_models_persist_relationships_and_is_busy_state(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $channel = TvChannelConfig::create([
            'name' => 'Canal Ciencia',
            'slug' => 'canal-ciencia',
            'language' => 'es',
            'resolution_profile' => '1080p30',
            'tts_provider' => 'google',
            'tts_voice' => 'es-ES-Neural2-A',
        ]);

        $schedule = TvDaySchedule::create([
            'channel_config_id' => $channel->id,
            'air_date' => '2026-11-26',
            'title' => 'Parrilla Jueves',
            'status' => 'scheduled',
            'slots' => [['hour' => '09:00', 'format' => 'pill']],
        ]);

        $job = BroadcastJob::create([
            'channel_id' => $channel->slug,
            'format' => 'pill',
            'title' => 'Agujeros negros',
            'raw_text' => 'Un agujero negro es una región del espacio-tiempo.',
            'language' => 'es',
            'stage' => BroadcastJobStage::RENDERING,
            'project_id' => $project->id,
        ]);

        $rundown = TvRundown::create([
            'project_id' => $project->id,
            'channel_config_id' => $channel->id,
            'day_schedule_id' => $schedule->id,
            'broadcast_job_id' => $job->id,
            'title' => 'Agujeros negros',
            'source_type' => 'wikipedia',
            'script_text' => 'Un agujero negro es una región del espacio-tiempo.',
            'visual_credit' => WikipediaRundownSynthesizer::VISUAL_CREDIT,
            'tts_provider' => 'google',
            'tts_voice' => 'es-ES-Neural2-A',
            'status' => 'draft',
        ]);

        $video = TvVideo::create([
            'project_id' => $project->id,
            'rundown_id' => $rundown->id,
            'broadcast_job_id' => $job->id,
            'title' => 'Agujeros negros',
            'file_path' => 'tv/agujeros-negros.mp4',
            'duration_seconds' => 45,
            'status' => 'ready',
        ]);

        $adSlot = TvAdSlot::create([
            'channel_config_id' => $channel->id,
            'day_schedule_id' => $schedule->id,
            'name' => 'Cortinilla Ciencia',
            'duration_seconds' => 15,
        ]);

        $this->assertTrue($rundown->isBusy());
        $this->assertSame($project->id, $rundown->project?->id);
        $this->assertSame($job->id, $rundown->broadcastJob?->id);
        $this->assertSame($video->id, $rundown->tvVideo?->id);
        $this->assertSame($project->id, $video->project?->id);
        $this->assertSame($rundown->id, $video->rundown?->id);
        $this->assertCount(1, $channel->daySchedules);
        $this->assertCount(1, $channel->adSlots);
        $this->assertSame($channel->id, $adSlot->channelConfig?->id);

        $job->update(['stage' => BroadcastJobStage::COMPLETED]);
        $rundown->refresh();
        $this->assertFalse($rundown->isBusy());
    }

    public function test_wikipedia_synthesizer_validates_urls_and_separates_visual_credit(): void
    {
        Http::fake([
            'https://es.wikipedia.org/api/rest_v1/page/summary/*' => Http::response([
                'title' => 'Telescopio espacial James Webb',
                'extract' => 'El telescopio espacial James Webb es un observatorio espacial desarrollado en colaboración internacional.',
                'content_urls' => [
                    'desktop' => [
                        'page' => 'https://es.wikipedia.org/wiki/Telescopio_espacial_James_Webb',
                    ],
                ],
            ], 200),
        ]);

        $synthesizer = app(WikipediaRundownSynthesizer::class);

        $parsed = $synthesizer->validateWikipediaUrl('https://es.wikipedia.org/wiki/Telescopio_espacial_James_Webb');
        $this->assertSame('es', $parsed['lang']);
        $this->assertSame('Telescopio_espacial_James_Webb', $parsed['title']);

        $result = $synthesizer->synthesize('https://es.wikipedia.org/wiki/Telescopio_espacial_James_Webb');

        $this->assertSame('Telescopio espacial James Webb', $result['title']);
        $this->assertSame(
            'El telescopio espacial James Webb es un observatorio espacial desarrollado en colaboración internacional.',
            $result['script_text']
        );
        $this->assertStringNotContainsString('CC BY-SA', $result['script_text']);
        $this->assertStringNotContainsString('Wikipedia', $result['script_text']);
        $this->assertSame('Fuente: Wikipedia · CC BY-SA 4.0', $result['visual_credit']);
        $this->assertSame(['https://es.wikipedia.org/wiki/Telescopio_espacial_James_Webb'], $result['sources']);
    }

    public function test_wikipedia_synthesizer_rejects_disallowed_urls(): void
    {
        $synthesizer = app(WikipediaRundownSynthesizer::class);

        $this->expectException(InvalidArgumentException::class);
        $synthesizer->validateWikipediaUrl('http://fr.wikipedia.org/wiki/Test');
    }
}
