<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class)->withTimestamps();
    }

    public function fitnessConnections(): HasMany
    {
        return $this->hasMany(FitnessConnection::class);
    }

    public function dailyStepLogs(): HasMany
    {
        return $this->hasMany(DailyStepLog::class);
    }

    public function creditWallet(): HasOne
    {
        return $this->hasOne(CreditWallet::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function stepBoosts(): HasMany
    {
        return $this->hasMany(StepBoost::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(UserLike::class);
    }

    public function receivedLikes(): HasMany
    {
        return $this->hasMany(UserLike::class, 'liked_user_id');
    }

    public function badgeAwards(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withPivot(['id', 'awarded_at', 'metadata'])
            ->withTimestamps();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function conversationParticipants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['id', 'joined_at', 'last_read_at', 'muted_until', 'left_at'])
            ->withTimestamps();
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function createdChallenges(): HasMany
    {
        return $this->hasMany(WalkingChallenge::class, 'inviter_id');
    }

    public function opponentChallenges(): HasMany
    {
        return $this->hasMany(WalkingChallenge::class, 'invitee_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function matchesAsUserOne(): HasMany
    {
        return $this->hasMany(StepMatch::class, 'user_one_id');
    }

    public function matchesAsUserTwo(): HasMany
    {
        return $this->hasMany(StepMatch::class, 'user_two_id');
    }

    public function profilePhotoUrl(): ?string
    {
        $photo = $this->profile?->profile_photo;

        if (! $photo) {
            return null;
        }

        return Str::startsWith($photo, ['http://', 'https://'])
            ? $photo
            : Storage::disk('public')->url($photo);
    }

    public function creditBalance(): int
    {
        return (int) ($this->creditWallet?->balance ?? 0);
    }

    public function isProfileCompleted(): bool
    {
        return (bool) ($this->profile?->profile_completed ?? false);
    }

    public function isFitnessConnected(): bool
    {
        return (bool) ($this->profile?->fitness_connected ?? false);
    }
}
