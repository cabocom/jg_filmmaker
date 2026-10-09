<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    protected $model = Character::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->firstName(),
            'bio' => fake()->paragraph(),
            'psychology' => fake()->sentence(),
            'costume_notes' => 'Vestuario cotidiano, tonos oscuros.',
            'custom_attributes' => [
                'archetype' => 'Protagonista',
                'age' => 35,
            ],
        ];
    }
}
