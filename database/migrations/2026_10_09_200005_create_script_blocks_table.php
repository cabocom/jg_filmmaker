<?php

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
        Schema::create('script_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('scene_id')->constrained('scenes')->cascadeOnDelete();
            $table->integer('block_order');
            $table->text('raw_text');
            $table->text('structured_text')->nullable();
            $table->text('screenplay_content')->nullable();
            $table->jsonb('user_locks')->default('{}');
            $table->enum('stage', ['raw', 'structured', 'screenplay', 'shots', 'editing'])->default('raw');
            $table->timestamps();

            $table->index('scene_id');
            $table->index(['scene_id', 'block_order']);
            $table->index('stage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('script_blocks');
    }
};
