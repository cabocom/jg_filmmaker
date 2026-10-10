<?php

namespace Tests\Feature;

use App\Livewire\Admin\PricingManager;
use App\Livewire\Admin\UserList;
use App\Models\AgentRequestLog;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\TokenPricingRate;
use App\Models\User;
use App\Services\AgentTelemetryService;
use Database\Seeders\TokenPricingRatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelAndTelemetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TokenPricingRatesSeeder::class);
    }

    public function test_standard_users_are_forbidden_403_from_admin_panel(): void
    {
        $standardUser = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($standardUser);

        $response = $this->get('/admin/users');
        $response->assertStatus(403);

        $responsePricing = $this->get('/admin/pricing');
        $responsePricing->assertStatus(403);

        $responseSecurity = $this->get('/admin/security');
        $responseSecurity->assertStatus(403);
    }

    public function test_admin_user_can_access_admin_dashboard_and_list_users(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Jefe',
            'is_admin' => true,
        ]);

        $regular = User::factory()->create([
            'name' => 'Guionista Junior',
            'is_admin' => false,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/admin/users');
        $response->assertStatus(200);
        $response->assertSee('Admin Jefe');
        $response->assertSee('Guionista Junior');
        $response->assertSee('Gobernanza de Usuarios');

        // Test Livewire toggle admin role
        Livewire::test(UserList::class)
            ->call('toggleAdmin', $regular->id);

        $regular->refresh();
        $this->assertTrue($regular->is_admin);
    }

    public function test_user_detail_displays_quantitative_metrics_with_strict_privacy_zero_script_leak(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $author = User::factory()->create(['name' => 'Autor Secreto']);

        $project = Project::factory()->create([
            'user_id' => $author->id,
            'title' => 'Obra Secreta de Ficción',
        ]);

        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. HABITACION SECRETA - NOCHE',
        ]);

        // Crear bloques con texto sensible de guión que NUNCA debe filtrarse en la auditoría
        $secretSensitiveDialogue = 'CONFIDENCIAL: El personaje revela la clave maestra secreta.';
        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'Texto bruto confidencial que no debe aparecer',
            'structured_text' => 'Tratamiento dramático confidencial clasificado',
            'screenplay_content' => $secretSensitiveDialogue,
            'stage' => 'screenplay',
        ]);

        // Registrar telemetría de IA para este proyecto
        AgentRequestLog::create([
            'project_id' => $project->id,
            'script_block_id' => $block->id,
            'tool_name' => 'screenplay_formatter',
            'input_tokens' => 1500,
            'output_tokens' => 800,
            'total_tokens' => 2300,
            'estimated_cost_usd' => 0.001517,
        ]);

        $this->actingAs($admin);

        $response = $this->get("/admin/users/{$author->id}");
        $response->assertStatus(200);

        // Verifica que se muestra el título del proyecto y las métricas cuantitativas
        $response->assertSee('Obra Secreta de Ficción');
        $response->assertSee('Guión:');
        $response->assertSee('2,300'); // Tokens consumidos

        // ESTRICTO CONTROL DE PRIVACIDAD: Cero exposición de textos del guion
        $response->assertDontSee($secretSensitiveDialogue);
        $response->assertDontSee('Texto bruto confidencial');
        $response->assertDontSee('Tratamiento dramático confidencial clasificado');
    }

    public function test_telemetry_service_calculates_usd_cost_and_persists_log(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $telemetryService = app(AgentTelemetryService::class);

        // Tarifa activa esperada por seeder: groq / llama-3.3-70b-versatile (Input $0.59 / 1M, Output $0.79 / 1M)
        $inputPayload = ['prompt' => str_repeat('palabra ', 100)]; // 800 chars -> 200 tokens
        $outputPayload = ['result' => str_repeat('respuesta ', 100)]; // 1000 chars -> 250 tokens

        $log = $telemetryService->record(
            projectId: $project->id,
            scriptBlockId: null,
            toolName: 'treatment_structuring',
            inputPayload: $inputPayload,
            outputPayload: $outputPayload,
            telemetry: [
                'usage' => [
                    'prompt_tokens' => 200,
                    'completion_tokens' => 250,
                ],
            ]
        );

        $this->assertNotNull($log->id);
        $this->assertEquals($project->id, $log->project_id);
        $this->assertEquals(200, $log->input_tokens);
        $this->assertEquals(250, $log->output_tokens);
        $this->assertEquals(450, $log->total_tokens);

        // Coste: (200 * 0.59 / 1M) + (250 * 0.79 / 1M) = 0.000118 + 0.0001975 = 0.0003155 -> round 6: 0.000316
        $expectedCost = round((200 * 0.59 / 1000000) + (250 * 0.79 / 1000000), 6);
        $this->assertEquals($expectedCost, (float) $log->estimated_cost_usd);

        $this->assertDatabaseHas('agent_request_logs', [
            'id' => $log->id,
            'project_id' => $project->id,
            'tool_name' => 'treatment_structuring',
        ]);
    }

    public function test_pricing_manager_allows_interactive_rate_management_and_active_toggle(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $groqRate = TokenPricingRate::where('model', 'llama-3.3-70b-versatile')->first();
        $geminiRate = TokenPricingRate::where('model', 'gemini-1.5-flash')->first();

        $this->assertTrue($groqRate->is_active);
        $this->assertFalse($geminiRate->is_active);

        // Activar Gemini
        Livewire::test(PricingManager::class)
            ->call('setActiveRate', $geminiRate->id);

        $groqRate->refresh();
        $geminiRate->refresh();
        $this->assertFalse($groqRate->is_active);
        $this->assertTrue($geminiRate->is_active);

        // Actualizar precio de Gemini
        Livewire::test(PricingManager::class)
            ->set("editingRates.{$geminiRate->id}.input_cost_per_million", 0.090000)
            ->set("editingRates.{$geminiRate->id}.output_cost_per_million", 0.350000)
            ->call('saveRate', $geminiRate->id);

        $geminiRate->refresh();
        $this->assertEquals(0.090000, (float) $geminiRate->input_cost_per_million);
        $this->assertEquals(0.350000, (float) $geminiRate->output_cost_per_million);
    }
}
