<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scene>
 */
class SceneFactory extends Factory
{
    protected $model = Scene::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'scene_order' => fake()->numberBetween(1, 100),
            'slugline' => 'INT. SALÓN - NOCHE 1',
            'location_id' => null,
            'time_of_day' => fake()->randomElement(['NOCHE', 'DÍA', 'ATARDECER', 'AMANECER']),
        ];
    }
}
