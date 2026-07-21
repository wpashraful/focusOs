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
        // 1. Industries
        Schema::create('industries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('priority')->default(0);
            $table->timestamps();
        });

        // 2. Root Keywords
        Schema::create('root_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('industry_id')->nullable()->constrained()->onDelete('set null');
            $table->string('keyword')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Search Variations
        Schema::create('search_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('root_keyword_id')->constrained()->onDelete('cascade');
            $table->string('keyword');
            $table->string('source')->default('AI'); // AI, Manual
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['root_keyword_id', 'keyword']);
        });

        // 4. Search Locations
        Schema::create('search_locations', function (Blueprint $table) {
            $table->id();
            $table->string('country', 10)->default('US');
            $table->string('state', 50);
            $table->string('city', 100);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('population')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['country', 'state', 'city']);
        });

        // 5. Search Coverages
        Schema::create('search_coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('variation_id')->constrained('search_variations')->onDelete('cascade');
            $table->foreignId('city_id')->constrained('search_locations')->onDelete('cascade');
            $table->boolean('searched')->default(false);
            $table->integer('lead_count')->default(0);
            $table->timestamp('last_scraped')->nullable();
            $table->string('status')->default('unchecked'); // unchecked, running, completed, failed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'variation_id', 'city_id']);
        });

        // 6. Import Sessions
        Schema::create('import_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('search_coverage_id')->nullable()->constrained('search_coverages')->onDelete('set null');
            $table->string('status')->default('running'); // running, completed, failed
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->integer('total_found')->default(0);
            $table->integer('imported')->default(0);
            $table->integer('duplicates')->default(0);
            $table->integer('failed')->default(0);
            $table->timestamps();
        });

        // 7. Add import_session_id to leads table
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('import_session_id')->nullable()->after('project_id')->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['import_session_id']);
            $table->dropColumn('import_session_id');
        });

        Schema::dropIfExists('import_sessions');
        Schema::dropIfExists('search_coverages');
        Schema::dropIfExists('search_locations');
        Schema::dropIfExists('search_variations');
        Schema::dropIfExists('root_keywords');
        Schema::dropIfExists('industries');
    }
};
