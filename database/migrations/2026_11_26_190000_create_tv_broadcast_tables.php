<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_channel_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->default('JG Filmmaker TV');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->string('language', 10)->default('es');
            $table->string('timezone', 64)->default('Europe/Madrid');
            $table->string('resolution_profile', 24)->default('1080p30');
            $table->string('tts_provider', 64)->nullable();
            $table->string('tts_voice', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('tv_day_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('channel_config_id')->nullable()->constrained('tv_channel_configs')->nullOnDelete();
            $table->date('air_date')->index();
            $table->string('title')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->json('slots')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tv_rundowns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignUuid('channel_config_id')->nullable()->constrained('tv_channel_configs')->nullOnDelete();
            $table->foreignUuid('day_schedule_id')->nullable()->constrained('tv_day_schedules')->nullOnDelete();
            $table->foreignUuid('broadcast_job_id')->nullable()->constrained('broadcast_jobs')->nullOnDelete();
            $table->string('title');
            $table->string('topic_or_url')->nullable();
            $table->string('source_type', 32)->default('manual');
            $table->string('format', 32)->default('pill');
            $table->string('language', 10)->default('es');
            $table->longText('script_text')->nullable();
            $table->string('visual_credit')->nullable();
            $table->json('sources')->nullable();
            $table->string('tts_provider', 64)->nullable();
            $table->string('tts_voice', 64)->nullable();
            $table->string('visual_mode', 32)->default('full_bleed');
            $table->string('resolution_profile', 24)->default('1080p30');
            $table->string('status', 32)->default('draft')->index();
            $table->integer('duration_seconds')->nullable();
            $table->integer('sort_order')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tv_videos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignUuid('rundown_id')->nullable()->constrained('tv_rundowns')->nullOnDelete();
            $table->foreignUuid('broadcast_job_id')->nullable()->constrained('broadcast_jobs')->nullOnDelete();
            $table->string('title');
            $table->string('file_path')->nullable();
            $table->string('podcast_path')->nullable();
            $table->string('short_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('resolution_profile', 24)->default('1080p30');
            $table->integer('duration_seconds')->nullable();
            $table->bigInteger('file_size_bytes')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tv_ad_slots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('channel_config_id')->nullable()->constrained('tv_channel_configs')->nullOnDelete();
            $table->foreignUuid('day_schedule_id')->nullable()->constrained('tv_day_schedules')->nullOnDelete();
            $table->string('name');
            $table->string('sponsor_name')->nullable();
            $table->string('media_path')->nullable();
            $table->integer('duration_seconds')->default(15);
            $table->string('placement', 32)->default('mid_roll');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_ad_slots');
        Schema::dropIfExists('tv_videos');
        Schema::dropIfExists('tv_rundowns');
        Schema::dropIfExists('tv_day_schedules');
        Schema::dropIfExists('tv_channel_configs');
    }
};
