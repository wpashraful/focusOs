<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DailyTarget extends Model
{
    protected $fillable = [
        'project_id', 'goal_id', 'label',
        'target_count', 'unit', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DailyLog::class);
    }

    public function todayLog(): HasOne
    {
        return $this->hasOne(DailyLog::class)
                    ->whereDate('log_date', today());
    }
}
