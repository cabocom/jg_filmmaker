<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('tv_rundowns')) {
            if (Schema::hasColumn('tv_rundowns', 'type')) {
                DB::table('tv_rundowns')->where('type', 'pill')->update(['type' => 'bio']);
            }

            if (Schema::hasColumn('tv_rundowns', 'format')) {
                DB::table('tv_rundowns')->where('format', 'pill')->update(['format' => 'bio']);
            }
        }

        if (Schema::hasTable('tv_videos') && Schema::hasColumn('tv_videos', 'type')) {
            DB::table('tv_videos')->where('type', 'pill')->update(['type' => 'bio']);
        }

        if (Schema::hasTable('broadcast_jobs') && Schema::hasColumn('broadcast_jobs', 'format')) {
            DB::table('broadcast_jobs')->where('format', 'pill')->update(['format' => 'bio']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible data normalization: cannot distinguish original 'pill' from existing 'bio' records safely.
    }
};
