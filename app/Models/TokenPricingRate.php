<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenPricingRate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'provider',
        'model',
        'input_cost_per_million',
        'output_cost_per_million',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'input_cost_per_million' => 'decimal:6',
            'output_cost_per_million' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }
}
