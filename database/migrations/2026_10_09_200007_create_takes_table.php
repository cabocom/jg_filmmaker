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
        Schema::create('takes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shot_id')->constrained('shots')->cascadeOnDelete();
            $table->integer('take_number');
            $table->string('file_path')->nullable();
            $table->boolean('is_good_take')->default(false);
            $table->text('editorial_notes')->nullable();
            $table->jsonb('technical_meta')->nullable();
            $table->timestamps();

            $table->index('shot_id');
            $table->index(['shot_id', 'take_number']);
            $table->index('is_good_take');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('takes');
    }
};
