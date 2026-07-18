<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phase_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('phase_name');
            $table->text('phase_goal')->nullable();
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->json('summary_json')->nullable();  // optional stats snapshot
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase_snapshots');
    }
};
