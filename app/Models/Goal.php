<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    protected $fillable = [
        'project_id', 'title', 'description',
        'target_value', 'current_value', 'unit',
        'priority', 'status',
    ];

    protected $casts = [
        'target_value'  => 'float',
        'current_value' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function dailyTargets(): HasMany
    {
        return $this->hasMany(DailyTarget::class);
    }

    // Computed progress percentage
    public function getProgressAttribute(): float
    {
        if ($this->target_value <= 0) return 0;
        return min(100, round(($this->current_value / $this->target_value) * 100, 1));
    }
}
