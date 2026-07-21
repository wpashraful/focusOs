<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RootKeyword extends Model
{
    protected $fillable = [
        'industry_id', 'keyword', 'slug', 'description', 'priority', 'is_system', 'is_active'
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function industry()
    {
        return $this->belongsTo(Industry::class);
    }

    public function variations()
    {
        return $this->hasMany(SearchVariation::class);
    }
}
