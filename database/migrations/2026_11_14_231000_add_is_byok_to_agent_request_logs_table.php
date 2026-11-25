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
        Schema::table('agent_request_logs', function (Blueprint $table) {
            $table->boolean('is_byok')->default(false)->after('total_tokens');
            $table->index(['user_id', 'is_byok']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_request_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_byok']);
            $table->dropColumn('is_byok');
        });
    }
};
