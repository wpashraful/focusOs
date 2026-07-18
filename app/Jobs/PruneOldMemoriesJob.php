<?php

namespace App\Jobs;

use App\Models\Memory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PruneOldMemoriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $deletedCount = Memory::where('importance_score', '<', 0.4)
                ->where('last_used_at', '<', now()->subDays(30))
                ->where('source', '!=', 'user_stated')
                ->delete();

            Log::info("Memory Pruning complete: deleted {$deletedCount} low-score old memories.");
        } catch (\Exception $e) {
            Log::error("Failed executing PruneOldMemoriesJob: " . $e->getMessage());
        }
    }
}
