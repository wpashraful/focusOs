<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceChunk extends Model
{
    protected $fillable = ['resource_id', 'chunk_index', 'content', 'summary', 'keyword_tags'];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}
