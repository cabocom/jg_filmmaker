<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tv_channel_configs', function (Blueprint $table): void {
            $table->string('channel_slug', 64)->default('ai-today')->index();
            $table->string('youtube_channel_id')->nullable();
            $table->text('youtube_oauth_encrypted')->nullable();
            $table->text('youtube_stream_key_encrypted')->nullable();
            $table->string('stream_key_fingerprint', 32)->nullable();
            $table->boolean('youtube_connected')->default(false);
            $table->json('voice_profiles_json')->nullable();
            $table->string('broadcast_profile', 24)->default('720p30');
            $table->string('videos_dir')->default('ai-today/videos');
            $table->string('ads_dir')->default('ai-today/ads');
            $table->timestamp('last_scanned_at')->nullable();
            $table->boolean('planning_in_progress')->default(false);
        });

        Schema::table('tv_day_schedules', function (Blueprint $table): void {
            $table->string('channel_slug', 64)->default('ai-today')->index();
            $table->date('schedule_date')->nullable()->index();
            $table->boolean('locked_by_admin')->default(false);
            $table->json('blocks_json')->nullable();
            $table->date('air_date')->nullable()->change();
        });

        Schema::table('tv_rundowns', function (Blueprint $table): void {
            $table->string('channel_slug', 64)->default('ai-today')->index();
            $table->string('type', 32)->default('pill');
            $table->string('source_kind', 32)->default('wikipedia');
            $table->json('sources_json')->nullable();
            $table->string('stage', 32)->default('draft')->index();
            $table->string('filmmaker_job_id', 64)->nullable();
            $table->uuid('tv_video_id')->nullable();
            $table->boolean('publish_to_podcast')->default(false);
            $table->boolean('generate_short')->default(false);
            $table->boolean('evergreen_fallback')->default(false);
        });

        Schema::table('tv_videos', function (Blueprint $table): void {
            $table->string('channel_slug', 64)->default('ai-today')->index();
            $table->string('type', 32)->default('pill');
            $table->string('source_ref', 500)->nullable();
            $table->longText('raw_text')->nullable();
            $table->string('filmmaker_job_id', 64)->nullable();
            $table->string('stage', 32)->default('pending')->index();
            $table->string('progress_text')->nullable();
            $table->text('error_message')->nullable();
            $table->string('mp4_url', 500)->nullable();
            $table->string('mp3_url', 500)->nullable();
            $table->string('short_url', 500)->nullable();
            $table->boolean('publish_to_podcast')->default(false);
            $table->boolean('generate_short')->default(false);
            $table->boolean('evergreen_fallback')->default(false);
            $table->string('origin', 32)->default('drive');
            $table->string('relative_media_path', 500)->nullable()->index();
            $table->boolean('broadcast_compliant')->default(false)->index();
            $table->string('compliance_profile', 24)->nullable();
            $table->json('compliance_reasons_json')->nullable();
            $table->string('normalization_job_id', 64)->nullable();
        });

        Schema::table('tv_ad_slots', function (Blueprint $table): void {
            $table->string('channel_slug', 64)->default('ai-today')->index();
            $table->string('asset_role', 32)->default('ad');
            $table->string('title')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('frequency_minutes')->default(0);
            $table->boolean('paid_promotion')->default(false);
            $table->string('stage', 32)->default('ready');
            $table->boolean('broadcast_compliant')->default(false);
            $table->string('compliance_profile', 24)->nullable();
            $table->json('compliance_reasons_json')->nullable();
            $table->string('normalization_job_id', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->string('name')->default('')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tv_ad_slots', function (Blueprint $table): void {
            $table->dropColumn([
                'channel_slug', 'asset_role', 'title', 'active', 'frequency_minutes',
                'paid_promotion', 'stage', 'broadcast_compliant', 'compliance_profile',
                'compliance_reasons_json', 'normalization_job_id', 'error_message',
            ]);
        });

        Schema::table('tv_videos', function (Blueprint $table): void {
            $table->dropColumn([
                'channel_slug', 'type', 'source_ref', 'raw_text', 'filmmaker_job_id',
                'stage', 'progress_text', 'error_message', 'mp4_url', 'mp3_url',
                'short_url', 'publish_to_podcast', 'generate_short', 'evergreen_fallback',
                'origin', 'relative_media_path', 'broadcast_compliant', 'compliance_profile',
                'compliance_reasons_json', 'normalization_job_id',
            ]);
        });

        Schema::table('tv_rundowns', function (Blueprint $table): void {
            $table->dropColumn([
                'channel_slug', 'type', 'source_kind', 'sources_json', 'stage',
                'filmmaker_job_id', 'tv_video_id', 'publish_to_podcast',
                'generate_short', 'evergreen_fallback',
            ]);
        });

        Schema::table('tv_day_schedules', function (Blueprint $table): void {
            $table->dropColumn(['channel_slug', 'schedule_date', 'locked_by_admin', 'blocks_json']);
        });

        Schema::table('tv_channel_configs', function (Blueprint $table): void {
            $table->dropColumn([
                'channel_slug', 'youtube_channel_id', 'youtube_oauth_encrypted',
                'youtube_stream_key_encrypted', 'stream_key_fingerprint', 'youtube_connected',
                'voice_profiles_json', 'broadcast_profile', 'videos_dir', 'ads_dir',
                'last_scanned_at', 'planning_in_progress',
            ]);
        });
    }
};
