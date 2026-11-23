<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BroadcastJobStage;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastJob extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'channel_id',
        'format',
        'title',
        'raw_text',
        'language',
        'sources',
        'visual_mode',
        'callback_url',
        'stage',
        'project_id',
        'script_block_id',
        'artifacts',
        'failed_stage',
        'error_message',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'artifacts' => 'array',
            'stage' => BroadcastJobStage::class,
        ];
    }

    /**
     * Project associated with the broadcast job.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Script block associated with the broadcast job.
     *
     * @return BelongsTo<ScriptBlock, $this>
     */
    public function scriptBlock(): BelongsTo
    {
        return $this->belongsTo(ScriptBlock::class);
    }
}
