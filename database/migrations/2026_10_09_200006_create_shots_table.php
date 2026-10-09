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
        Schema::create('shots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('scene_id')->constrained('scenes')->cascadeOnDelete();
            $table->foreignUuid('script_block_id')->nullable()->constrained('script_blocks')->nullOnDelete();
            $table->integer('shot_number');
            $table->string('shot_type'); // e.g., PG, PM, PP, PLANO DETALLE
            $table->text('description');
            $table->timestamps();

            $table->index('scene_id');
            $table->index('script_block_id');
            $table->index(['scene_id', 'shot_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shots');
    }
};
