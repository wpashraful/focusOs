<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\AI\MemoryUpdater;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtractMemoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $userMsg;
    protected string $assistantReply;
    protected ?Project $project;

    /**
     * Create a new job instance.
     */
    public function __construct(string $userMsg, string $assistantReply, ?Project $project = null)
    {
        $this->userMsg = $userMsg;
        $this->assistantReply = $assistantReply;
        $this->project = $project;
    }

    /**
     * Execute the job.
     */
    public function handle(MemoryUpdater $updater): void
    {
        try {
            $updater->process($this->userMsg, $this->assistantReply, $this->project);
        } catch (\Exception $e) {
            Log::error("Failed executing ExtractMemoryJob: " . $e->getMessage());
        }
    }
}
