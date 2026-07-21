<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('ai_tools')->updateOrInsert(
            ['name' => 'query_leads'],
            [
                'description' => "Search and query leads inside the user's active workspace. You can search by business name, website, phone, location/address, rating, lead score, or pipeline status.",
                'handler_class' => \App\Services\AI\Tools\QueryLeads::class,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ai_tools')->where('name', 'query_leads')->delete();
    }
};
