<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AILog extends Model
{
    protected $table = 'ai_logs';

    protected $fillable = [
        'user_id', 'project_id', 'provider', 'model', 'action',
        'prompt_tokens', 'completion_tokens', 'total_tokens',
        'latency_ms', 'cost', 'error',
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
