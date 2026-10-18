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
        Schema::table('script_blocks', function (Blueprint $table) {
            $table->index(['scene_id', 'stage'], 'script_blocks_scene_stage_idx');
        });

        Schema::table('agent_request_logs', function (Blueprint $table) {
            $table->index('created_at', 'agent_request_logs_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('script_blocks', function (Blueprint $table) {
            $table->dropIndex('script_blocks_scene_stage_idx');
        });

        Schema::table('agent_request_logs', function (Blueprint $table) {
            $table->dropIndex('agent_request_logs_created_at_idx');
        });
    }
};
