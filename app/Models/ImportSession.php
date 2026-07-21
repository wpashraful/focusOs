<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportSession extends Model
{
    protected $fillable = [
        'workspace_id', 'project_id', 'search_coverage_id', 'status', 'started_at',
        'finished_at', 'total_found', 'imported', 'duplicates', 'failed'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'total_found' => 'integer',
        'imported' => 'integer',
        'duplicates' => 'integer',
        'failed' => 'integer',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function coverage()
    {
        return $this->belongsTo(SearchCoverage::class, 'search_coverage_id');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }
}
