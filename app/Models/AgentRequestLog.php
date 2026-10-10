<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRequestLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'script_block_id',
        'tool_name',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'total_tokens' => 'integer',
            'estimated_cost_usd' => 'decimal:6',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scriptBlock(): BelongsTo
    {
        return $this->belongsTo(ScriptBlock::class);
    }
}
