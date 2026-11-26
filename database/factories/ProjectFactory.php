<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'synopsis' => fake()->paragraph(),
            'status' => fake()->randomElement(['draft', 'in_progress', 'completed']),
            'category' => ProjectCategory::PERSONAL->value,
        ];
    }

    public function aiVideo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => ProjectCategory::AI_VIDEO->value,
        ]);
    }
}
