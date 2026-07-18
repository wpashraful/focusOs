<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('ai_provider_id')->nullable()->constrained('ai_providers')->onDelete('set null');
            $table->string('model_name')->default('gemini-1.5-flash');
            $table->text('system_prompt')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->unsignedInteger('max_tokens')->default(2048);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_ai_settings');
    }
};
