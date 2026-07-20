<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingAction extends Model
{
    use HasFactory;

    protected $table = 'pending_actions';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'action_type',
        'payload',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'expires_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }

    public function isUndoable(): bool
    {
        return $this->status === 'confirmed' && now()->diffInMinutes($this->updated_at) <= 5;
    }
}
