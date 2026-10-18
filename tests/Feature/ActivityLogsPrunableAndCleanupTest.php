<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityLogsPrunableAndCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_activity_log_is_prunable_after_90_days(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $oldLog = UserActivityLog::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'action' => 'edit_raw',
            'entity_type' => 'script_block',
            'entity_id' => (string) Str::uuid(),
            'description' => 'Log antiguo',
            'snapshot' => ['raw_text' => 'Texto antiguo'],
        ]);

        DB::table('user_activity_logs')
            ->where('id', $oldLog->id)
            ->update(['created_at' => now()->subDays(95)]);

        $recentLog = UserActivityLog::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'action' => 'edit_raw',
            'entity_type' => 'script_block',
            'entity_id' => (string) Str::uuid(),
            'description' => 'Log reciente',
            'snapshot' => ['raw_text' => 'Texto reciente'],
        ]);

        $prunableCount = (new UserActivityLog)->prunable()->count();
        $this->assertSame(1, $prunableCount);
        $this->assertSame($oldLog->id, (new UserActivityLog)->prunable()->first()->id);
    }

    public function test_deleting_project_purges_associated_activity_logs_cascade(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        UserActivityLog::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'action' => 'edit_raw',
            'entity_type' => 'script_block',
            'entity_id' => (string) Str::uuid(),
            'description' => 'Actividad de proyecto a borrar',
            'snapshot' => ['raw_text' => 'Contenido confidencial'],
        ]);

        $this->assertSame(1, UserActivityLog::where('project_id', $project->id)->count());

        $project->delete();

        $this->assertSame(0, UserActivityLog::where('project_id', $project->id)->count());
    }
}
