<?php

namespace Tests\Feature;

use App\Models\Interest;
use App\Models\StepMatch;
use App\Models\User;
use App\Models\UserLike;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoverySwipeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_excludes_swiped_and_matched_users_and_returns_pagination(): void
    {
        $current = $this->profiledUser('Current User', 25);
        $available = $this->profiledUser('Available User', 26);
        $swiped = $this->profiledUser('Swiped User', 27);
        $matched = $this->profiledUser('Matched User', 28);

        UserLike::query()->create([
            'user_id' => $current->id,
            'liked_user_id' => $swiped->id,
            'action' => 'dislike',
        ]);
        StepMatch::query()->create([
            'user_one_id' => min($current->id, $matched->id),
            'user_two_id' => max($current->id, $matched->id),
            'match_percent' => 80,
            'status' => 'active',
        ]);

        $this->actingAs($current, 'sanctum')
            ->getJson('/api/discovery')
            ->assertOk()
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.id', $available->id)
            ->assertJsonPath('data.users.0.name', 'Available User')
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonStructure([
                'data' => [
                    'users' => [[
                        'id',
                        'name',
                        'age',
                        'profile_photo_url',
                        'bio',
                        'interests',
                        'avg_steps',
                        'distance',
                        'match_percent',
                        'is_verified',
                    ]],
                ],
            ]);
    }

    public function test_mutual_like_creates_one_match_conversation_and_two_participants(): void
    {
        $current = $this->profiledUser('Current User', 25);
        $target = $this->profiledUser('Target User', 26);
        UserLike::query()->create([
            'user_id' => $target->id,
            'liked_user_id' => $current->id,
            'action' => 'like',
        ]);

        $this->actingAs($current, 'sanctum')
            ->postJson('/api/swipe', [
                'target_user_id' => $target->id,
                'action' => 'like',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_match', true)
            ->assertJsonPath('data.match.matched_user.id', $target->id);

        $this->assertDatabaseCount('step_matches', 1);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);

        $this->actingAs($current, 'sanctum')
            ->getJson('/api/matches')
            ->assertOk()
            ->assertJsonCount(1, 'data.matches')
            ->assertJsonPath('data.matches.0.user.id', $target->id);
    }

    public function test_super_like_costs_25_credits_and_duplicate_swipe_is_rejected(): void
    {
        $current = $this->profiledUser('Current User', 25);
        $target = $this->profiledUser('Target User', 26);
        $current->creditWallet()->create([
            'balance' => 50,
            'lifetime_earned' => 50,
        ]);

        $this->actingAs($current, 'sanctum')
            ->postJson('/api/swipe', [
                'target_user_id' => $target->id,
                'action' => 'super_like',
            ])
            ->assertCreated()
            ->assertJsonPath('data.credits_spent', 25)
            ->assertJsonPath('data.credit_balance', 25)
            ->assertJsonPath('data.is_match', false);

        $this->actingAs($current, 'sanctum')
            ->postJson('/api/swipe', [
                'target_user_id' => $target->id,
                'action' => 'like',
            ])
            ->assertStatus(409)
            ->assertJsonPath('status', false);

        $this->assertDatabaseCount('user_likes', 1);
        $this->assertDatabaseCount('credit_transactions', 1);
    }

    public function test_self_swipe_and_unfunded_super_like_are_rejected(): void
    {
        $current = $this->profiledUser('Current User', 25);
        $target = $this->profiledUser('Target User', 26);
        $current->creditWallet()->create(['balance' => 10]);

        $this->actingAs($current, 'sanctum')
            ->postJson('/api/swipe', [
                'target_user_id' => $current->id,
                'action' => 'like',
            ])
            ->assertUnprocessable();

        $this->actingAs($current, 'sanctum')
            ->postJson('/api/swipe', [
                'target_user_id' => $target->id,
                'action' => 'super_like',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Insufficient credit balance.');

        $this->assertDatabaseCount('user_likes', 0);
        $this->assertDatabaseCount('step_matches', 0);
    }

    private function profiledUser(string $name, int $age): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->profile()->create([
            'full_name' => $name,
            'age' => $age,
            'gender' => 'male',
            'bio' => "{$name} bio",
            'latitude' => 9.9312,
            'longitude' => 76.2673,
            'profile_completed' => true,
        ]);
        $interest = Interest::query()->firstOrCreate(
            ['slug' => 'running'],
            ['name' => 'Running', 'is_active' => true]
        );
        $user->interests()->syncWithoutDetaching([$interest->id]);
        $user->dailyStepLogs()->create([
            'provider' => 'google_fit',
            'log_date' => today(),
            'steps' => 10000,
            'goal_steps' => 10000,
        ]);

        return $user;
    }
}
