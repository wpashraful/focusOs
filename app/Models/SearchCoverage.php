<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchCoverage extends Model
{
    protected $table = 'search_coverages';

    protected $fillable = [
        'workspace_id', 'variation_id', 'city_id', 'searched', 'lead_count', 'last_scraped', 'status', 'notes'
    ];

    protected $casts = [
        'searched' => 'boolean',
        'lead_count' => 'integer',
        'last_scraped' => 'datetime',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function variation()
    {
        return $this->belongsTo(SearchVariation::class, 'variation_id');
    }

    public function location()
    {
        return $this->belongsTo(SearchLocation::class, 'city_id');
    }

    public function session()
    {
        return $this->hasOne(ImportSession::class, 'search_coverage_id');
    }
}
