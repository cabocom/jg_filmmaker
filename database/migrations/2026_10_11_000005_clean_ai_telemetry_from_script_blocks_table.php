<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $blocks = DB::table('script_blocks')->whereNotNull('user_locks')->get();

        foreach ($blocks as $block) {
            $locks = json_decode((string) $block->user_locks, true);
            if (is_array($locks) && isset($locks['ai_telemetry'])) {
                unset($locks['ai_telemetry']);
                DB::table('script_blocks')
                    ->where('id', $block->id)
                    ->update([
                        'user_locks' => json_encode($locks),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se revierte la purga de telemetría obsoleta
    }
};
