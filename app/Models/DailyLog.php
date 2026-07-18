<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyLog extends Model
{
    protected $fillable = ['daily_target_id', 'log_date', 'achieved_count', 'notes'];

    protected $casts = ['log_date' => 'date'];

    public function dailyTarget(): BelongsTo
    {
        return $this->belongsTo(DailyTarget::class);
    }
}
