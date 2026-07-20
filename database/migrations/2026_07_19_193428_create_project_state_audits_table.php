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
        Schema::create('project_state_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('conversation_id')->nullable()->constrained()->onDelete('set null');
            $table->string('goal_title');
            $table->string('operation'); // increment, set_total, decrement
            $table->integer('value');
            $table->integer('previous_value');
            $table->integer('new_value');
            $table->string('entity')->nullable();
            $table->string('router'); // rule_engine, llm_extractor
            $table->decimal('confidence', 3, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_state_audits');
    }
};
