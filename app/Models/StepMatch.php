<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StepMatch extends Model
{
    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'match_percent',
        'status',
        'matched_at',
        'unmatched_by',
        'unmatched_at',
    ];

    protected function casts(): array
    {
        return [
            'match_percent' => 'integer',
            'matched_at' => 'datetime',
            'unmatched_at' => 'datetime',
        ];
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function unmatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unmatched_by');
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }
}
