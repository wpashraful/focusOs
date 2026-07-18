<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAISetting extends Model
{
    protected $table = 'project_ai_settings';

    protected $fillable = ['project_id', 'ai_provider_id', 'model_name', 'system_prompt', 'temperature', 'max_tokens'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }
}
