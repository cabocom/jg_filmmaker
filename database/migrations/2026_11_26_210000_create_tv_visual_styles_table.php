<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_visual_styles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->text('prompt_base');
            $table->text('characteristics')->nullable();
            $table->string('reference_image_path', 500)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('tv_rundowns', function (Blueprint $table): void {
            $table->foreignUuid('visual_style_id')
                ->nullable()
                ->constrained('tv_visual_styles')
                ->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignUuid('visual_style_id')
                ->nullable()
                ->constrained('tv_visual_styles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('visual_style_id');
        });

        Schema::table('tv_rundowns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('visual_style_id');
        });

        Schema::dropIfExists('tv_visual_styles');
    }
};
