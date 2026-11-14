<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('takes', function (Blueprint $table) {
            $table->string('proxy_file_path')->nullable()->after('file_path');
            $table->string('thumbnail_path')->nullable()->after('proxy_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('takes', function (Blueprint $table) {
            $table->dropColumn(['proxy_file_path', 'thumbnail_path']);
        });
    }
};
