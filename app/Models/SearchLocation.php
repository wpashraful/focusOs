<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchLocation extends Model
{
    protected $fillable = [
        'country', 'state', 'city', 'latitude', 'longitude', 'population', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'double',
        'longitude' => 'double',
        'population' => 'integer',
    ];

    public function coverages()
    {
        return $this->hasMany(SearchCoverage::class, 'city_id');
    }
}
