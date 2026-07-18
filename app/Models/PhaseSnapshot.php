<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhaseSnapshot extends Model
{
    protected $fillable = [
        'project_id', 'phase_name', 'phase_goal',
        'started_at', 'ended_at', 'summary_json',
    ];

    protected $casts = [
        'started_at'   => 'date',
        'ended_at'     => 'date',
        'summary_json' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
