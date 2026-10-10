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
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('action'); // 'delete_block', 'delete_scene', etc.
            $table->string('entity_type'); // 'ScriptBlock', 'Scene', etc.
            $table->string('entity_id')->nullable();
            $table->string('description');
            $table->json('snapshot')->nullable(); // Contenido previo para trazabilidad y auditoría
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
