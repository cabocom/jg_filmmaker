<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_request_logs', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->index(['user_id', 'created_at']);
        });

        // Poblado retroactivo de user_id a partir de projects.user_id
        if (Schema::hasColumn('agent_request_logs', 'project_id')) {
            DB::statement('
                UPDATE agent_request_logs
                SET user_id = (SELECT user_id FROM projects WHERE projects.id = agent_request_logs.project_id)
                WHERE user_id IS NULL AND project_id IS NOT NULL
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_request_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
