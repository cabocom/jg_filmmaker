<?php

namespace Database\Seeders;

use App\Models\TokenPricingRate;
use Illuminate\Database\Seeder;

class TokenPricingRatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rates = [
            [
                'provider' => 'groq',
                'model' => 'llama-3.3-70b-versatile',
                'input_cost_per_million' => 0.590000,
                'output_cost_per_million' => 0.790000,
                'is_active' => true,
            ],
            [
                'provider' => 'groq',
                'model' => 'llama-3.1-8b-instant',
                'input_cost_per_million' => 0.050000,
                'output_cost_per_million' => 0.080000,
                'is_active' => false,
            ],
            [
                'provider' => 'gemini',
                'model' => 'gemini-1.5-flash',
                'input_cost_per_million' => 0.075000,
                'output_cost_per_million' => 0.300000,
                'is_active' => false,
            ],
            [
                'provider' => 'ollama',
                'model' => 'local-model',
                'input_cost_per_million' => 0.000000,
                'output_cost_per_million' => 0.000000,
                'is_active' => false,
            ],
        ];

        foreach ($rates as $rate) {
            TokenPricingRate::updateOrCreate(
                [
                    'provider' => $rate['provider'],
                    'model' => $rate['model'],
                ],
                [
                    'input_cost_per_million' => $rate['input_cost_per_million'],
                    'output_cost_per_million' => $rate['output_cost_per_million'],
                    'is_active' => $rate['is_active'],
                ]
            );
        }
    }
}
