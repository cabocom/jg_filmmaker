<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProjectManager;
use App\Livewire\Admin\UserList;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPaginationAndUploadConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_livewire_temporary_upload_is_configured_for_100mb_video_takes(): void
    {
        // ISSUE-026: Coherencia de límites de upload Livewire
        $rules = config('livewire.temporary_file_upload.rules');
        $this->assertIsArray($rules);
        $this->assertContains('max:102400', $rules);
        $this->assertEquals(30, config('livewire.temporary_file_upload.max_upload_time'));
        $this->assertEquals('throttle:15,1', config('livewire.temporary_file_upload.middleware'));
    }

    public function test_user_list_component_paginates_users(): void
    {
        // ISSUE-022: Paginación en UserList
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->count(30)->create();

        Livewire::actingAs($admin)
            ->test(UserList::class)
            ->assertViewHas('users', function ($users) {
                return $users instanceof LengthAwarePaginator && $users->perPage() === 25;
            });
    }

    public function test_project_manager_component_paginates_projects_and_lazy_loads_users(): void
    {
        // ISSUE-022: Paginación y carga diferida en ProjectManager
        $admin = User::factory()->create(['is_admin' => true]);
        Project::factory()->count(25)->create(['user_id' => $admin->id]);

        $component = Livewire::actingAs($admin)
            ->test(ProjectManager::class)
            ->assertViewHas('projects', function ($projects) {
                return $projects instanceof LengthAwarePaginator && $projects->perPage() === 20;
            })
            ->assertViewHas('allUsers', function ($allUsers) {
                // Cuando el modal está cerrado, la lista de usuarios no se carga innecesariamente
                return $allUsers->isEmpty();
            });

        // Al abrir el modal de transferencia, se cargan los usuarios
        $firstProject = Project::first();
        $component->call('openTransferModal', $firstProject->id)
            ->assertViewHas('allUsers', function ($allUsers) {
                return $allUsers->isNotEmpty();
            });
    }
}
