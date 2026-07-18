<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#6366f1');   // hex color for sidebar
            $table->string('icon', 10)->nullable();            // emoji icon
            $table->enum('status', ['active', 'paused', 'completed'])->default('active');

            // Phase embedded — no separate phases table
            $table->string('current_phase_name')->nullable();
            $table->text('current_phase_goal')->nullable();
            $table->date('phase_started_at')->nullable();
            $table->date('phase_ends_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
