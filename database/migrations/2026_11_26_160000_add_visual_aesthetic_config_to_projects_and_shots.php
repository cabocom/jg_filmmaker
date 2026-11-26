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
        Schema::table('projects', function (Blueprint $table) {
            $table->json('visual_aesthetic_config')->nullable()->after('storyboard_style');
        });

        Schema::table('shots', function (Blueprint $table) {
            $table->json('visual_aesthetic_config')->nullable()->after('storyboard_style');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('visual_aesthetic_config');
        });

        Schema::table('shots', function (Blueprint $table) {
            $table->dropColumn('visual_aesthetic_config');
        });
    }
};
