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
        Schema::table('characters', function (Blueprint $table) {
            $table->string('reference_image_path')->nullable()->after('costume_notes');
        });

        Schema::table('shots', function (Blueprint $table) {
            $table->string('storyboard_image_path')->nullable()->after('description');
            $table->string('storyboard_style')->nullable()->after('storyboard_image_path');
            $table->text('storyboard_prompt')->nullable()->after('storyboard_style');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('storyboard_style')->default('grayscale_sketch')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('storyboard_style');
        });

        Schema::table('shots', function (Blueprint $table) {
            $table->dropColumn(['storyboard_image_path', 'storyboard_style', 'storyboard_prompt']);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('reference_image_path');
        });
    }
};
