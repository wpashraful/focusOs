<?php

namespace App\Events;

use App\Models\DailyLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DailyLogUpdated
{
    use Dispatchable, SerializesModels;

    public DailyLog $log;

    public function __construct(DailyLog $log)
    {
        $this->log = $log;
    }
}
