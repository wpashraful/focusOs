<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->text('summary')->nullable();
            $table->text('keyword_tags')->nullable();
            $table->timestamps();

            // MySQL FULLTEXT index for high-speed keyword keyword search
            $table->fulltext(['content', 'keyword_tags']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_chunks');
    }
};
