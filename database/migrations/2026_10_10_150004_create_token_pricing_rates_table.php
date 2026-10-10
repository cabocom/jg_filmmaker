<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('token_pricing_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider'); // 'groq', 'gemini', 'ollama', 'openai'
            $table->string('model');
            $table->decimal('input_cost_per_million', 10, 6)->default(0);
            $table->decimal('output_cost_per_million', 10, 6)->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['provider', 'model']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_pricing_rates');
    }
};
