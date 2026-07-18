<?php

namespace App\Jobs;

use App\Models\ResourceChunk;
use App\Services\AI\AIProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ResourceChunk $chunk;

    /**
     * Create a new job instance.
     */
    public function __construct(ResourceChunk $chunk)
    {
        $this->chunk = $chunk;
    }

    /**
     * Execute the job.
     */
    public function handle(AIProviderInterface $aiProvider): void
    {
        try {
            $prompt = "Summarize the document chunk below and extract 5-10 comma-separated keyword tags that represent its key concepts.\n"
                    . "Format your output strictly as a JSON object matching this schema:\n"
                    . "{\n"
                    . "  \"summary\": \"string (brief summary of chunk)\",\n"
                    . "  \"keyword_tags\": \"string (comma-separated list e.g. marketing, sales, q3_goals)\"\n"
                    . "}\n\n"
                    . "Content:\n"
                    . "\"{$this->chunk->content}\"\n\n"
                    . "JSON:";

            $res = $aiProvider->chat([
                ['role' => 'user', 'content' => $prompt]
            ], [
                'temperature' => 0.2,
                'max_tokens'  => 300,
            ]);

            $text = trim($res['text']);
            $text = preg_replace('/^```json\s*/i', '', $text);
            $text = preg_replace('/```$/', '', $text);

            $data = json_decode($text, true);

            $this->chunk->update([
                'summary'      => $data['summary'] ?? null,
                'keyword_tags' => $data['keyword_tags'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error("GenerateSummaryJob Failed on Chunk #{$this->chunk->id}: " . $e->getMessage());
        }
    }
}
