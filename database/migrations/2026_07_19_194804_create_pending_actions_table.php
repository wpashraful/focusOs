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
        Schema::create('pending_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('message_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action_type')->default('goal_update');
            $table->text('payload'); // json field using text for maximum local compatibility
            $table->string('status')->default('pending'); // pending, confirmed, cancelled, expired, undone
            $table->dateTime('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_actions');
    }
};
