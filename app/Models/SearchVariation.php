<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchVariation extends Model
{
    protected $fillable = ['root_keyword_id', 'keyword', 'source', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function rootKeyword()
    {
        return $this->belongsTo(RootKeyword::class);
    }

    public function coverages()
    {
        return $this->hasMany(SearchCoverage::class, 'variation_id');
    }
}
