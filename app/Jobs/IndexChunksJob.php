<?php

namespace App\Jobs;

use App\Models\Resource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexChunksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Resource $resource;

    /**
     * Create a new job instance.
     */
    public function __construct(Resource $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Verify chunks exist
            if ($this->resource->chunks()->count() === 0) {
                throw new \Exception("No chunks were indexed.");
            }

            $this->resource->update(['status' => 'ready']);
            Log::info("Resource Ingestion Complete: Resource #{$this->resource->id} is ready.");

        } catch (\Exception $e) {
            Log::error("IndexChunksJob Failed: " . $e->getMessage());
            $this->resource->update(['status' => 'failed']);
        }
    }
}
