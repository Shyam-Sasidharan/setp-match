<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'age',
        'gender',
        'bio',
        'walking_preferences',
        'profile_photo',
        'latitude',
        'longitude',
        'city',
        'state',
        'country',
        'profile_completed',
        'fitness_connected',
        'daily_step_goal',
        'subscription_plan',
    ];

    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'profile_completed' => 'boolean',
            'fitness_connected' => 'boolean',
            'daily_step_goal' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
