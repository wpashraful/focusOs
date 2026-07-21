<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAudit extends Model
{
    protected $fillable = [
        'lead_id',
        'strengths',
        'gaps',
        'suggestions',
        'cold_email_pitch',
        'background',
    ];

    protected $casts = [
        'strengths' => 'array',
        'gaps' => 'array',
        'suggestions' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
