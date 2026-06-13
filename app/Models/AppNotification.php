<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class AppNotification extends Model
{
    public const TYPES = [
        'match',
        'challenge',
        'badge',
        'credits',
        'goal',
        'system',
    ];

    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'title',
        'body',
        'data',
        'read_at',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function createFor(
        User $user,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?User $actor = null
    ): self {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported notification type: {$type}");
        }

        return self::query()->create([
            'user_id' => $user->id,
            'actor_id' => $actor?->id,
            'type' => $type,
            'title' => $title,
            'body' => $message,
            'data' => $data === [] ? null : $data,
        ]);
    }

    public function markAsRead(): self
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }

        return $this;
    }

    public function dismiss(): self
    {
        if ($this->dismissed_at === null) {
            $this->forceFill(['dismissed_at' => now()])->save();
        }

        return $this;
    }
}
