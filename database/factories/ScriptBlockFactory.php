<?php

namespace Database\Factories;

use App\Models\Scene;
use App\Models\ScriptBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScriptBlock>
 */
class ScriptBlockFactory extends Factory
{
    protected $model = ScriptBlock::class;

    public function definition(): array
    {
        return [
            'scene_id' => Scene::factory(),
            'block_order' => 1,
            'raw_text' => 'El protagonista entra apresuradamente en la habitación buscando las llaves.',
            'structured_text' => 'Juan accede a la estancia con premura y registra la mesa de madera.',
            'screenplay_content' => "INT. SALÓN - NOCHE 1\n\nJUAN entra apresuradamente.\n\nJUAN\n¿Dónde las habré dejado?",
            'user_locks' => [
                'locked_fields' => ['dialogue_speaker'],
                'manual_diffs' => [],
                'pinned_terms' => ['Juan' => 'Javier'],
            ],
            'stage' => 'raw',
        ];
    }
}
