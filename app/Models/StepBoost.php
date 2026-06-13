<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StepBoost extends Model
{
    protected $fillable = [
        'user_id',
        'daily_step_log_id',
        'boost_date',
        'boost_steps',
        'credits_spent',
        'status',
        'applied_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'boost_date' => 'date',
            'boost_steps' => 'integer',
            'credits_spent' => 'integer',
            'applied_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyStepLog(): BelongsTo
    {
        return $this->belongsTo(DailyStepLog::class);
    }
}
