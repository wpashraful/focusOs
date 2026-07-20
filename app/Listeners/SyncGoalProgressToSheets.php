<?php

namespace App\Listeners;

use App\Events\ProjectStateUpdated;
use App\Services\GoogleSheetsService;
use Illuminate\Support\Facades\Log;

class SyncGoalProgressToSheets
{
    protected GoogleSheetsService $sheetsService;

    public function __construct(GoogleSheetsService $sheetsService)
    {
        $this->sheetsService = $sheetsService;
    }

    /**
     * Handle the event.
     */
    public function handle(ProjectStateUpdated $event): void
    {
        $syncMode = env('GOOGLE_SHEETS_SYNC_MODE', 'export');
        if ($syncMode === 'import') {
            return;
        }

        $audit = $event->audit;
        $row = [
            now()->toDateTimeString(),
            $event->project->name,
            $event->goal->title,
            $audit->operation,
            $audit->value,
            $audit->previous_value,
            $audit->new_value,
            $audit->router
        ];

        try {
            $success = $this->sheetsService->appendRow($row);
            if ($success) {
                Log::info("Synced update for Goal \"{$event->goal->title}\" to Google Sheets.");
            } else {
                Log::warning("Failed to sync update to Google Sheets. Verify settings.");
            }
        } catch (\Exception $e) {
            Log::error("Google Sheets Sync Error: " . $e->getMessage());
        }
    }
}
