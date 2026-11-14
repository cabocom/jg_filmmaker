<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use App\Services\ScriptPipelineService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneShotsResequencingTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_blocks_in_same_scene_have_unique_sequential_shot_numbers(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block1 = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'screenplay_content' => "INT. SALÓN - DÍA\n\n--- shot (PG) ---\nJavier entra a la sala.\n\n--- shot (PM) ---\nObserva la mesa con sospecha.",
            'stage' => 'screenplay',
        ]);

        $block2 = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 2,
            'screenplay_content' => "INT. SALÓN - DÍA\n\n--- shot (PP) ---\nPrimer plano del sobre sellado.\n\n--- shot (PLANO DETALLE) ---\nDetalle del lacre rojo roto.",
            'stage' => 'screenplay',
        ]);

        $pipeline = app(ScriptPipelineService::class);

        // Desglosar bloque 1
        $shotsBlock1 = $pipeline->generateShotsBreakdown($block1);
        $this->assertCount(2, $shotsBlock1);

        // Desglosar bloque 2
        $shotsBlock2 = $pipeline->generateShotsBreakdown($block2);
        $this->assertCount(2, $shotsBlock2);

        // Todos los planos de la escena deben tener shot_number correlativo y único 1, 2, 3, 4
        $sceneShots = Shot::where('scene_id', $scene->id)->orderBy('shot_number')->get();
        $this->assertCount(4, $sceneShots);

        $shotNumbers = $sceneShots->pluck('shot_number')->all();
        $this->assertSame([1, 2, 3, 4], $shotNumbers);

        // Los planos de block1 deben ser 1 y 2
        $this->assertSame([1, 2], $block1->shots()->orderBy('shot_number')->pluck('shot_number')->all());

        // Los planos de block2 deben ser 3 y 4 (sin colisión con block1)
        $this->assertSame([3, 4], $block2->shots()->orderBy('shot_number')->pluck('shot_number')->all());
    }

    public function test_takes_table_enforces_unique_constraint_on_shot_id_and_take_number(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $block = ScriptBlock::factory()->create(['scene_id' => $scene->id]);
        $shot = Shot::factory()->create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
        ]);

        Take::create([
            'shot_id' => $shot->id,
            'take_number' => 1,
            'is_good_take' => false,
        ]);

        $this->expectException(QueryException::class);

        // Intentar insertar una toma duplicada para el mismo plano debe disparar QueryException por UNIQUE constraint
        Take::create([
            'shot_id' => $shot->id,
            'take_number' => 1,
            'is_good_take' => true,
        ]);
    }
}
