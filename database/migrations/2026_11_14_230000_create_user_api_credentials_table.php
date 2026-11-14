<?php

declare(strict_types=1);

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
        Schema::create('user_api_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 50); // google, groq, openai, anthropic
            $table->text('encrypted_api_key');
            $table->string('key_fingerprint', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_api_credentials');
    }
};
