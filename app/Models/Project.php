<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Step 3 models — imported for relationship return types
// (PHP resolves these at runtime; explicit imports aid IDE autocomplete)
use App\Models\Goal;
use App\Models\Task;
use App\Models\Routine;
use App\Models\DailyTarget;

class Project extends Model
{
    protected $fillable = [
        'workspace_id', 'name', 'description', 'color', 'icon', 'status',
        'current_phase_name', 'current_phase_goal',
        'phase_started_at', 'phase_ends_at',
    ];

    protected $casts = [
        'phase_started_at' => 'date',
        'phase_ends_at'    => 'date',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function phaseSnapshots(): HasMany
    {
        return $this->hasMany(PhaseSnapshot::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function routine(): HasMany
    {
        return $this->hasMany(Routine::class)->where('is_active', true)->limit(1);
    }

    public function dailyTargets(): HasMany
    {
        return $this->hasMany(DailyTarget::class)->where('is_active', true);
    }
}
