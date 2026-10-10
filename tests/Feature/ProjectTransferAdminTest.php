<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProjectManager;
use App\Livewire\Admin\UserDetail;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use Database\Seeders\TokenPricingRatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectTransferAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TokenPricingRatesSeeder::class);
    }

    public function test_standard_users_are_forbidden_403_from_admin_projects(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        $response = $this->get('/admin/projects');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_projects_catalog_and_view_metadata(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create(['name' => 'Directora Ana', 'email' => 'ana@estudio.test', 'is_admin' => false]);

        $project = Project::create([
            'user_id' => $owner->id,
            'title' => 'El Secreto de la Niebla',
            'status' => 'in_progress',
        ]);

        $scene = Scene::create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. ESTUDIO - DIA 1',
            'time_of_day' => 'DIA',
        ]);

        ScriptBlock::create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'TEXTO_CONFIDENCIAL_QUE_NO_DEBE_EXPONERSE_EN_ADMIN',
            'screenplay_content' => 'GUION_SUPER_SECRETO_DEL_AUTOR',
            'stage' => 'screenplay',
        ]);

        $this->actingAs($admin);

        $response = $this->get('/admin/projects');
        $response->assertStatus(200);
        $response->assertSee('El Secreto de la Niebla');
        $response->assertSee('Directora Ana');
        $response->assertSee('ana@estudio.test');

        // Garantizar estricta privacidad: los textos confidenciales nunca se exponen en la vista
        $response->assertDontSee('TEXTO_CONFIDENCIAL_QUE_NO_DEBE_EXPONERSE_EN_ADMIN');
        $response->assertDontSee('GUION_SUPER_SECRETO_DEL_AUTOR');
    }

    public function test_admin_can_transfer_project_from_user_detail_component(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $originalOwner = User::factory()->create(['name' => 'Autor Original', 'email' => 'original@test.es']);
        $newOwner = User::factory()->create(['name' => 'Nuevo Titular', 'email' => 'nuevo@test.es']);

        $project = Project::create([
            'user_id' => $originalOwner->id,
            'title' => 'Cicatrices del Silencio',
            'status' => 'draft',
        ]);

        $this->actingAs($admin);

        Livewire::test(UserDetail::class, ['user' => $originalOwner])
            ->call('openTransferModal', $project->id)
            ->assertSet('showTransferModal', true)
            ->assertSet('selectedProjectId', $project->id)
            ->set('targetUserId', $originalOwner->id)
            ->call('transferProject')
            ->assertHasErrors(['targetUserId'])
            ->set('targetUserId', $newOwner->id)
            ->call('transferProject')
            ->assertHasNoErrors()
            ->assertSet('showTransferModal', false);

        $this->assertEquals($newOwner->id, $project->fresh()->user_id);
    }

    public function test_admin_can_transfer_project_from_project_manager_component(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ownerA = User::factory()->create(['name' => 'Cineasta A', 'email' => 'a@test.es']);
        $ownerB = User::factory()->create(['name' => 'Cineasta B', 'email' => 'b@test.es']);

        $project = Project::create([
            'user_id' => $ownerA->id,
            'title' => 'Horizontes Perdidos',
            'status' => 'in_progress',
        ]);

        $this->actingAs($admin);

        Livewire::test(ProjectManager::class)
            ->call('openTransferModal', $project->id)
            ->assertSet('showTransferModal', true)
            ->assertSet('selectedProjectId', $project->id)
            ->assertSet('currentOwnerId', $ownerA->id)
            ->set('targetUserId', $ownerA->id)
            ->call('transferProject')
            ->assertHasErrors(['targetUserId'])
            ->set('targetUserId', $ownerB->id)
            ->call('transferProject')
            ->assertHasNoErrors()
            ->assertSet('showTransferModal', false);

        $this->assertEquals($ownerB->id, $project->fresh()->user_id);
    }
}
