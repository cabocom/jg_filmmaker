<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureDemoUserAuthenticated;
use App\Livewire\Admin\PricingManager;
use App\Livewire\Admin\ProjectManager;
use App\Livewire\Admin\SecurityLogsViewer;
use App\Livewire\Admin\UserList;
use App\Models\AdminAuditLog;
use App\Models\Project;
use App\Models\SecurityBreachLog;
use App\Models\TokenPricingRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSecurityAndDemoIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_to_admin_or_protected_routes_redirects_to_login(): void
    {
        // ISSUE-028: La petición sin sesión no se autologuea y redirige a login
        $responseAdmin = $this->get('/admin/users');
        $responseAdmin->assertRedirect(route('login'));
        $this->assertFalse(Auth::check());

        $responseProjects = $this->get('/projects');
        $responseProjects->assertRedirect(route('login'));
        $this->assertFalse(Auth::check());
    }

    public function test_ensure_demo_user_authenticated_does_not_autologin_in_testing(): void
    {
        // ISSUE-028: Verificar directamente el middleware EnsureDemoUserAuthenticated
        $middleware = new EnsureDemoUserAuthenticated;
        $request = Request::create('/projects', 'GET');

        $this->assertFalse(Auth::check());

        $middleware->handle($request, function ($req) {
            return response('OK');
        });

        // Debe mantenerse sin autenticar en entorno testing
        $this->assertFalse(Auth::check());
    }

    public function test_non_admin_user_cannot_access_admin_routes(): void
    {
        // ISSUE-021: Usuario sin rol de admin recibe 403 Forbidden
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get('/admin/users')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/admin/projects')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/admin/pricing')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/admin/security')
            ->assertForbidden();
    }

    public function test_non_admin_cannot_invoke_admin_component_actions(): void
    {
        // ISSUE-021: Verificación de Gate 'admin' a nivel de métodos Livewire
        $regularUser = User::factory()->create(['is_admin' => false]);
        $targetUser = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($regularUser)
            ->test(UserList::class)
            ->call('toggleAdmin', $targetUser->id)
            ->assertForbidden();
    }

    public function test_admin_cannot_revoke_own_privileges(): void
    {
        // ISSUE-021: Prevención de auto-revocación
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(UserList::class)
            ->call('toggleAdmin', $admin->id)
            ->assertSee('No puedes revocar tus propios privilegios de administrador.');

        $this->assertTrue($admin->fresh()->is_admin);
        $this->assertEquals(0, AdminAuditLog::count());
    }

    public function test_admin_cannot_revoke_only_existing_admin(): void
    {
        // ISSUE-021: Garantizar al menos un admin activo
        $admin = User::factory()->create(['is_admin' => true]);
        $anotherAdmin = User::factory()->create(['is_admin' => true]);

        // Si hay 2 admins, se puede alternar el segundo
        Livewire::actingAs($admin)
            ->test(UserList::class)
            ->call('toggleAdmin', $anotherAdmin->id);

        $this->assertFalse($anotherAdmin->fresh()->is_admin);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'toggle_admin',
            'target_id' => $anotherAdmin->id,
        ]);

        // Ahora solo queda 1 admin (el actual). Si intenta degradarse a sí mismo o el único admin:
        Livewire::actingAs($admin)
            ->test(UserList::class)
            ->call('toggleAdmin', $admin->id)
            ->assertSee('No puedes revocar tus propios privilegios');

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_admin_transfer_project_logs_audit_trail(): void
    {
        // ISSUE-021: Trazabilidad en transferencias de proyectos
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create(['is_admin' => false]);

        $project = Project::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Obra Confidencial',
        ]);

        Livewire::actingAs($admin)
            ->test(ProjectManager::class)
            ->call('openTransferModal', $project->id)
            ->set('targetUserId', $target->id)
            ->call('transferProject')
            ->assertHasNoErrors();

        $this->assertEquals($target->id, $project->fresh()->user_id);

        $this->assertDatabaseHas('admin_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'transfer_project',
            'target_id' => $project->id,
        ]);
    }

    public function test_admin_cannot_delete_active_rate_and_logs_pricing_actions(): void
    {
        // ISSUE-021: Protección de tarifa activa y trazabilidad
        $admin = User::factory()->create(['is_admin' => true]);

        $activeRate = TokenPricingRate::create([
            'provider' => 'groq',
            'model' => 'llama-3.3-70b-versatile',
            'input_cost_per_million' => 0.59,
            'output_cost_per_million' => 0.79,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(PricingManager::class)
            ->call('deleteRate', $activeRate->id)
            ->assertSee('No se puede eliminar la tarifa activa');

        $this->assertNotNull(TokenPricingRate::find($activeRate->id));

        // Actualizar precio de tarifa y verificar log
        Livewire::actingAs($admin)
            ->test(PricingManager::class)
            ->set("editingRates.{$activeRate->id}.input_cost_per_million", 0.65)
            ->call('saveRate', $activeRate->id);

        $this->assertEquals(0.65, (float) $activeRate->fresh()->input_cost_per_million);

        $this->assertDatabaseHas('admin_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'update_rate_pricing',
            'target_id' => $activeRate->id,
        ]);
    }

    public function test_admin_clearing_security_logs_records_audit_trail_before_deletion(): void
    {
        // ISSUE-021: Auditoría forense de purga de seguridad
        $admin = User::factory()->create(['is_admin' => true]);

        SecurityBreachLog::create([
            'ip_address' => '1.2.3.4',
            'user_agent' => 'Mozilla/5.0',
            'attempted_at' => now(),
            'reason' => 'OTP_REPLAY_ATTACK',
        ]);

        $this->assertEquals(1, SecurityBreachLog::count());

        Livewire::actingAs($admin)
            ->test(SecurityLogsViewer::class)
            ->call('clearLogs');

        $this->assertEquals(0, SecurityBreachLog::count());

        $this->assertDatabaseHas('admin_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'purge_security_breach_logs',
        ]);
    }
}
