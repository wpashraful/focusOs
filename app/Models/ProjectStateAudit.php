<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectStateAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'conversation_id',
        'goal_title',
        'operation',
        'value',
        'previous_value',
        'new_value',
        'entity',
        'router',
        'confidence',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
