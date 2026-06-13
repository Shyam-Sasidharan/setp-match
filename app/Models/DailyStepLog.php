<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyStepLog extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'log_date',
        'steps',
        'goal_steps',
        'distance_km',
        'calories',
        'heart_rate',
        'active_minutes',
        'step_credits_awarded',
        'goal_bonus_awarded',
        'source_data',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'steps' => 'integer',
            'goal_steps' => 'integer',
            'distance_km' => 'decimal:3',
            'calories' => 'decimal:2',
            'heart_rate' => 'integer',
            'active_minutes' => 'integer',
            'step_credits_awarded' => 'integer',
            'goal_bonus_awarded' => 'boolean',
            'source_data' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stepBoosts(): HasMany
    {
        return $this->hasMany(StepBoost::class);
    }
}
