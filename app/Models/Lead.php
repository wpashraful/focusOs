<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $fillable = [
        'workspace_id',
        'project_id',
        'import_session_id',
        'uuid',
        'name',
        'website',
        'phone',
        'email',
        'rating',
        'reviews_count',
        'address',
        'status',
        'lead_score',
        'source',
    ];

    protected $casts = [
        'rating' => 'double',
        'reviews_count' => 'integer',
        'lead_score' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class, 'import_session_id');
    }

    public function socials(): HasMany
    {
        return $this->hasMany(LeadSocial::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(LeadAudit::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(LeadEmail::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }
}
