<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('broadcast_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('channel_id', 120)->index();
            $table->string('format', 32);
            $table->string('title', 255);
            $table->longText('raw_text');
            $table->string('language', 16)->default('es');
            $table->json('sources')->nullable();
            $table->string('visual_mode', 64)->default('storyboard');
            $table->string('callback_url', 500)->nullable();
            $table->string('stage', 32)->default('RAW')->index();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignUuid('script_block_id')->nullable()->constrained('script_blocks')->nullOnDelete();
            $table->json('artifacts')->nullable();
            $table->string('failed_stage', 32)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_jobs');
    }
};
