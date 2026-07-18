<?php

namespace App\Listeners;

use App\Events\TaskCompleted;
use Illuminate\Support\Facades\Log;

class UpdateDashboardStats
{
    public function handle(TaskCompleted $event): void
    {
        Log::info("Dashboard stats listener: Task Completed -> ID: " . $event->task->id);
    }
}
