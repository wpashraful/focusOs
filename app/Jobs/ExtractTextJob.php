<?php

namespace App\Jobs;

use App\Models\Resource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ExtractTextJob implements ShouldQueue
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
            $path = $this->resource->file_path;

            if (!Storage::exists($path)) {
                throw new \Exception("File does not exist: {$path}");
            }

            $rawText = "";
            $ext = pathinfo($this->resource->name, PATHINFO_EXTENSION);

            if (in_array(strtolower($ext), ['txt', 'md', 'json', 'csv'])) {
                $rawText = Storage::get($path);
            } elseif (strtolower($ext) === 'pdf') {
                // PDF Parsing fallback: try Smalot PDF Parser if available, otherwise read raw
                if (class_exists(\Smalot\PdfParser\Parser::class)) {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile(Storage::path($path));
                    $rawText = $pdf->getText();
                } else {
                    // Primitive pdf text stripper fallback
                    $rawText = shell_exec('pdftotext ' . escapeshellarg(Storage::path($path)) . ' -');
                    if (empty($rawText)) {
                        $rawText = Storage::get($path); // last resort raw read
                    }
                }
            } else {
                $rawText = Storage::get($path);
            }

            // Save extracted text temporarily on resource object or pass to next job via database
            // Let's store raw text in a temp file or save it directly for ChunkTextJob
            $tempPath = 'temp_extracted/' . $this->resource->id . '.txt';
            Storage::put($tempPath, $rawText);

            // Chain to chunk text
            ChunkTextJob::dispatch($this->resource, $tempPath);

        } catch (\Exception $e) {
            Log::error("ExtractTextJob Failed: " . $e->getMessage());
            $this->resource->update(['status' => 'failed']);
        }
    }
}
