<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLike extends Model
{
    protected $fillable = [
        'user_id',
        'liked_user_id',
        'action',
        'credits_spent',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'credits_spent' => 'integer',
            'acted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liked_user_id');
    }
}
