<?php

namespace App\Jobs;

use App\Models\Resource;
use App\Models\ResourceChunk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ChunkTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Resource $resource;
    protected string $tempPath;

    /**
     * Create a new job instance.
     */
    public function __construct(Resource $resource, string $tempPath)
    {
        $this->resource = $resource;
        $this->tempPath = $tempPath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if (!Storage::exists($this->tempPath)) {
                throw new \Exception("Temporary extracted text file not found.");
            }

            $text = Storage::get($this->tempPath);
            Storage::delete($this->tempPath); // Clean up temp file

            // Split text by whitespace to get words
            $words = preg_split('/\s+/u', $text);
            $totalWords = count($words);

            $chunkSize = 512;
            $overlap = 50;
            $step = $chunkSize - $overlap;

            $chunkIndex = 0;

            for ($i = 0; $i < $totalWords; $i += $step) {
                $slice = array_slice($words, $i, $chunkSize);
                $content = implode(' ', $slice);

                if (empty(trim($content))) {
                    continue;
                }

                $chunk = ResourceChunk::create([
                    'resource_id' => $this->resource->id,
                    'chunk_index' => $chunkIndex++,
                    'content'     => $content,
                ]);

                // Dispatch summary generation job per chunk
                GenerateSummaryJob::dispatch($chunk);

                // Stop if we hit the end
                if ($i + $chunkSize >= $totalWords) {
                    break;
                }
            }

            // Chain to Index Job to complete
            IndexChunksJob::dispatch($this->resource);

        } catch (\Exception $e) {
            Log::error("ChunkTextJob Failed: " . $e->getMessage());
            $this->resource->update(['status' => 'failed']);
        }
    }
}
