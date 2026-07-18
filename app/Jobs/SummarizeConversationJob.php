<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\AI\ConversationSummarizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SummarizeConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Conversation $conversation;

    /**
     * Create a new job instance.
     */
    public function __construct(Conversation $conversation)
    {
        $this->conversation = $conversation;
    }

    /**
     * Execute the job.
     */
    public function handle(ConversationSummarizer $summarizer): void
    {
        try {
            $summarizer->summarize($this->conversation);
            Log::info("Conversation #{$this->conversation->id} successfully summarized.");
        } catch (\Exception $e) {
            Log::error("Failed to summarize Conversation #{$this->conversation->id}: " . $e->getMessage());
        }
    }
}
