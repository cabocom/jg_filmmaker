<?php

namespace Database\Factories;

use App\Models\Shot;
use App\Models\Take;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Take>
 */
class TakeFactory extends Factory
{
    protected $model = Take::class;

    public function definition(): array
    {
        return [
            'shot_id' => Shot::factory(),
            'take_number' => 1,
            'file_path' => 'media/projects/demo-project/scenes/demo-scene/takes/take-1.mp4',
            'is_good_take' => fake()->boolean(40),
            'editorial_notes' => 'Toma con buen encuadre, cortar inicio tras claqueta.',
            'technical_meta' => [
                'duration_seconds' => 14.5,
                'resolution' => '3840x2160',
                'codec' => 'prores422hq',
                'fps' => 24.0,
            ],
        ];
    }
}
