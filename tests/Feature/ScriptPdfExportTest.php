<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\ScriptExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScriptPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_export_screenplay_as_pdf_via_http_route(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'title' => 'EL MISTERIO DEL VALLE',
            'synopsis' => 'Una aventura en las montañas.',
        ]);

        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. CABAÑA - NOCHE',
        ]);

        ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'screenplay_content' => "GERMÁN mira por la ventana con inquietud.\n\nGERMÁN\nYa vienen.",
        ]);

        $response = $this->actingAs($user)->get(route('projects.export-pdf', $project));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('el-misterio-del-valle-guion.pdf', (string) $response->headers->get('Content-Disposition'));

        // El contenido binario del PDF debe comenzar con la firma canónica %PDF
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_unauthorized_user_cannot_export_another_users_screenplay_pdf(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $project = Project::factory()->create([
            'user_id' => $owner->id,
            'title' => 'PROYECTO PRIVADO',
        ]);

        $response = $this->actingAs($stranger)->get(route('projects.export-pdf', $project));

        $response->assertStatus(403);
    }

    public function test_export_pdf_via_livewire_action(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'title' => 'OBRA CINEMATOGRAFICA',
        ]);

        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'EXT. BOSQUE - DIA',
        ]);

        ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'screenplay_content' => "LEIDI MACARENA camina entre los árboles.\n\nLEIDI MACARENA\nNo hay salida.",
        ]);

        $this->actingAs($user);

        Livewire::test('studio.script-editor', ['project' => $project])
            ->call('exportPdf')
            ->assertFileDownloaded('obra-cinematografica-guion.pdf');
    }

    public function test_script_export_service_parses_screenplay_elements(): void
    {
        $service = app(ScriptExportService::class);

        $content = <<<'TEXT'
INT. SALÓN - DÍA

GERMÁN se sienta en el sofá.

JUAN (nervioso)
¿Qué hacemos ahora?

GERMÁN
Esperar.
TEXT;

        $elements = $service->parseBlockContent($content);

        $this->assertNotEmpty($elements);
        $types = array_column($elements, 'type');

        $this->assertContains('scene_heading', $types);
        $this->assertContains('action', $types);
        $this->assertContains('character', $types);
        $this->assertContains('parenthetical', $types);
        $this->assertContains('dialogue', $types);
    }
}
