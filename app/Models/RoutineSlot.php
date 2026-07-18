<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineSlot extends Model
{
    protected $fillable = [
        'routine_id', 'start_time', 'end_time',
        'label', 'category', 'color', 'sort_order',
    ];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    // Duration in minutes
    public function getDurationMinutesAttribute(): int
    {
        $start = strtotime($this->start_time);
        $end   = strtotime($this->end_time);
        return (int) round(($end - $start) / 60);
    }
}
