<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_target_id')->constrained()->onDelete('cascade');
            $table->date('log_date');
            $table->unsignedInteger('achieved_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['daily_target_id', 'log_date']); // one log per target per day
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_logs');
    }
};
