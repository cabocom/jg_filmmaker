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
        Schema::create('tv_media_normalizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('relative_path', 500);
            $table->string('target_profile', 16)->default('720p30');
            $table->string('stage', 24)->default('queued');
            $table->text('error_message')->nullable();
            $table->json('probe_before')->nullable();
            $table->json('probe_after')->nullable();
            $table->timestamps();

            $table->index(['relative_path', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tv_media_normalizations');
    }
};
