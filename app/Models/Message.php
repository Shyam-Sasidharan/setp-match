<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'walking_challenge_id',
        'type',
        'body',
        'audio_path',
        'audio_duration_seconds',
        'metadata',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'audio_duration_seconds' => 'integer',
            'metadata' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function walkingChallenge(): BelongsTo
    {
        return $this->belongsTo(WalkingChallenge::class);
    }
}
