<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Memory extends Model
{
    protected $fillable = [
        'user_id', 'project_id', 'key', 'value',
        'importance_score', 'confidence', 'source', 'last_used_at',
    ];

    protected $casts = [
        'importance_score' => 'float',
        'confidence'       => 'float',
        'last_used_at'     => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
