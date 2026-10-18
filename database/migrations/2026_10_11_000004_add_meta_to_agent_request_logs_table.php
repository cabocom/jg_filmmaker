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
        Schema::table('agent_request_logs', function (Blueprint $table) {
            $table->jsonb('meta')->nullable()->after('estimated_cost_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_request_logs', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
