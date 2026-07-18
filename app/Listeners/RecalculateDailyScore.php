<?php

namespace App\Listeners;

use App\Events\DailyLogUpdated;
use Illuminate\Support\Facades\Log;

class RecalculateDailyScore
{
    public function handle(DailyLogUpdated $event): void
    {
        Log::info("Daily score recalculation listener: Daily Log Updated -> ID: " . $event->log->id);
    }
}
