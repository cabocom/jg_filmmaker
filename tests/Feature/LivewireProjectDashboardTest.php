<?php

namespace Tests\Feature;

use App\Livewire\Projects\ProjectList;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireProjectDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_dashboard_renders_successfully_for_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email' => 'filmmaker@cabocom.net',
            'name' => 'Javier Gálvez',
            'plan_tier' => 'pro',
            'paid_project_slots' => 5,
        ]);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'title' => 'El Susurro del Viento',
            'synopsis' => 'Un thriller psicológico en la costa.',
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertStatus(200);
        $response->assertSee('El Susurro del Viento');
        $response->assertSee('Proyectos Cinematográficos');
        $response->assertSee(substr($project->id, 0, 8));
    }

    public function test_can_create_a_new_project_through_livewire(): void
    {
        $user = User::factory()->create([
            'paid_project_slots' => 5,
        ]);

        Livewire::actingAs($user)
            ->test(ProjectList::class)
            ->set('title', 'Nuevo Largometraje Noir')
            ->set('synopsis', 'Historia detectivesca nocturna.')
            ->set('status', 'draft')
            ->call('createProject')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('projects', [
            'user_id' => $user->id,
            'title' => 'Nuevo Largometraje Noir',
            'status' => 'draft',
        ]);
    }
}
