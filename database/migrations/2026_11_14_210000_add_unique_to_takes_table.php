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
            $table->dropIndex(['shot_id', 'take_number']);
            $table->unique(['shot_id', 'take_number']);
        });
    }

    public function down(): void
    {
        Schema::table('takes', function (Blueprint $table) {
            $table->dropUnique(['shot_id', 'take_number']);
            $table->index(['shot_id', 'take_number']);
        });
    }
};
