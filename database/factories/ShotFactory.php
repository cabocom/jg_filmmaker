<?php

namespace Database\Factories;

use App\Models\Scene;
use App\Models\Shot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shot>
 */
class ShotFactory extends Factory
{
    protected $model = Shot::class;

    public function definition(): array
    {
        return [
            'scene_id' => Scene::factory(),
            'script_block_id' => null,
            'shot_number' => 1,
            'shot_type' => fake()->randomElement(['PG', 'PM', 'PP', 'PLANO DETALLE']),
            'description' => fake()->sentence(),
        ];
    }
}
