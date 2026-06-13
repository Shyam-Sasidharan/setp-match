<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalkingChallenge extends Model
{
    protected $fillable = [
        'conversation_id',
        'step_match_id',
        'inviter_id',
        'invitee_id',
        'title',
        'description',
        'metric',
        'target_value',
        'challenge_date',
        'status',
        'responded_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'integer',
            'challenge_date' => 'date',
            'responded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function stepMatch(): BelongsTo
    {
        return $this->belongsTo(StepMatch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
