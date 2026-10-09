<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => strtoupper(fake()->word()).' PRINCIPAL',
            'type' => fake()->randomElement(['INT', 'EXT', 'INT/EXT']),
            'art_department_notes' => 'Paredes envejecidas, mobiliario vintage.',
            'lighting_notes' => 'Luz cenital tenue con dominante fría.',
            'address_reference' => fake()->address(),
        ];
    }
}
