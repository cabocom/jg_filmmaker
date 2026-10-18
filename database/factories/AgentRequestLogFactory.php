<?php

namespace Database\Factories;

use App\Models\AgentRequestLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentRequestLog>
 */
class AgentRequestLogFactory extends Factory
{
    protected $model = AgentRequestLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
            'script_block_id' => null,
            'tool_name' => 'treatment_structuring',
            'input_tokens' => 100,
            'output_tokens' => 50,
            'total_tokens' => 150,
            'estimated_cost_usd' => 0.0001,
            'meta' => null,
        ];
    }
}
