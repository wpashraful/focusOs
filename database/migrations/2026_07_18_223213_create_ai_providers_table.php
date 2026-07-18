<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider_key')->unique(); // e.g. gemini, openai, openrouter, local
            $table->string('api_base')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_tools')->default(true);
            $table->boolean('supports_streaming')->default(true);
            $table->boolean('supports_embeddings')->default(false);
            $table->unsignedInteger('provider_dimension')->default(1536);
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
