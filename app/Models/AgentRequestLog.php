<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class AgentRequestLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'project_id',
        'script_block_id',
        'tool_name',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'total_tokens' => 'integer',
            'estimated_cost_usd' => 'decimal:6',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (AgentRequestLog $log): void {
            if ($log->user_id) {
                Cache::forget("user:{$log->user_id}:tokens_used_today");
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
