<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIProvider extends Model
{
    protected $table = 'ai_providers';

    protected $fillable = ['name', 'provider_key', 'api_base', 'is_active', 'config'];

    protected $casts = [
        'is_active' => 'boolean',
        'config'    => 'array',
    ];

    public function projectSettings(): HasMany
    {
        return $this->hasMany(ProjectAISetting::class);
    }
}
