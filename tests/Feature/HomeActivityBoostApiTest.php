<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\DailyStepLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeActivityBoostApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_dashboard_returns_fitness_credits_and_nearby_walkers(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'full_name' => 'Alex Walker',
            'age' => 24,
            'gender' => 'male',
            'profile_completed' => true,
            'subscription_plan' => 'gold',
        ]);
        $user->creditWallet()->create(['balance' => 100]);
        $user->dailyStepLogs()->create([
            'provider' => 'google_fit',
            'log_date' => today(),
            'steps' => 5000,
            'goal_steps' => 10000,
        ]);

        $nearby = User::factory()->create(['name' => 'Jordan Vance']);
        $nearby->profile()->create([
            'full_name' => 'Jordan Vance',
            'age' => 25,
            'gender' => 'male',
            'profile_completed' => true,
            'city' => 'Kochi',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('data.subscription_plan', 'gold')
            ->assertJsonPath('data.today_steps', 5000)
            ->assertJsonPath('data.progress_percent', 50)
            ->assertJsonPath('data.credit_balance', 100)
            ->assertJsonPath('data.featured_challenge.boost_steps', 1200)
            ->assertJsonPath('data.nearby_walkers.0.name', 'Jordan Vance');
    }

    public function test_activity_analytics_returns_totals_and_badges(): void
    {
        $user = User::factory()->create();
        $user->dailyStepLogs()->create([
            'provider' => 'google_fit',
            'log_date' => today(),
            'steps' => 10000,
            'goal_steps' => 10000,
            'distance_km' => 7.5,
            'calories' => 400,
            'heart_rate' => 78,
            'active_minutes' => 90,
        ]);
        $badge = Badge::query()->create([
            'name' => 'Early Bird',
            'slug' => 'early-bird',
            'is_active' => true,
        ]);
        $user->badges()->attach($badge, ['awarded_at' => now()]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/activity/analytics')
            ->assertOk()
            ->assertJsonPath('data.today_steps', 10000)
            ->assertJsonPath('data.weekly_steps', 10000)
            ->assertJsonPath('data.current_streak', 1)
            ->assertJsonPath('data.total_distance', 7.5)
            ->assertJsonPath('data.calories_burned', 400)
            ->assertJsonPath('data.avg_heart_rate', 78)
            ->assertJsonPath('data.active_hours', 1.5)
            ->assertJsonPath('data.badges.0.name', 'Early Bird');
    }

    public function test_user_can_buy_up_to_three_step_boosts_per_day(): void
    {
        $user = User::factory()->create();
        $user->creditWallet()->create([
            'balance' => 100,
            'lifetime_earned' => 100,
        ]);
        DailyStepLog::query()->create([
            'user_id' => $user->id,
            'provider' => 'google_fit',
            'log_date' => today(),
            'steps' => 1000,
            'goal_steps' => 10000,
        ]);

        foreach ([1, 2, 3] as $boostNumber) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/steps/boost', ['steps' => 1200])
                ->assertOk()
                ->assertJsonPath('data.boosts_used_today', $boostNumber);
        }

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/steps/boost', ['steps' => 1200])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Daily step boost limit reached.');

        $this->assertDatabaseCount('step_boosts', 3);
        $this->assertDatabaseCount('credit_transactions', 3);
        $this->assertDatabaseHas('credit_wallets', [
            'user_id' => $user->id,
            'balance' => 40,
        ]);
        $this->assertDatabaseHas('daily_step_logs', [
            'user_id' => $user->id,
            'steps' => 4600,
        ]);
    }

    public function test_step_boost_requires_1200_steps_and_sufficient_credits(): void
    {
        $user = User::factory()->create();
        $user->creditWallet()->create(['balance' => 10]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/steps/boost', ['steps' => 500])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/steps/boost', ['steps' => 1200])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Insufficient credit balance.');

        $this->assertDatabaseCount('step_boosts', 0);
    }
}
