<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FitnessCreditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_connect_a_fitness_provider(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fitness/connect', [
                'provider' => 'google_fit',
                'provider_user_id' => 'google-user-123',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.connection.provider', 'google_fit')
            ->assertJsonPath('data.fitness_connected', true);

        $this->assertDatabaseHas('fitness_connections', [
            'user_id' => $user->id,
            'provider' => 'google_fit',
            'status' => 'connected',
        ]);
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'fitness_connected' => true,
        ]);
    }

    public function test_step_sync_awards_only_new_credits_and_daily_bonus_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fitness/sync-steps', [
                'provider' => 'google_fit',
                'date' => today()->toDateString(),
                'steps' => 3000,
                'goal_steps' => 10000,
                'distance_km' => 2.25,
                'calories' => 120,
                'active_minutes' => 25,
            ])
            ->assertOk()
            ->assertJsonPath('data.credits_earned', 15)
            ->assertJsonPath('data.credit_balance', 15)
            ->assertJsonPath('data.today.steps', 3000);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fitness/sync-steps', [
                'provider' => 'google_fit',
                'date' => today()->toDateString(),
                'steps' => 10000,
                'goal_steps' => 10000,
                'distance_km' => 7.5,
                'calories' => 400,
                'active_minutes' => 80,
            ])
            ->assertOk()
            ->assertJsonPath('data.credits_earned', 85)
            ->assertJsonPath('data.credit_balance', 100)
            ->assertJsonPath('data.today.progress_percent', 100);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fitness/sync-steps', [
                'provider' => 'google_fit',
                'date' => today()->toDateString(),
                'steps' => 10000,
                'goal_steps' => 10000,
            ])
            ->assertOk()
            ->assertJsonPath('data.credits_earned', 0)
            ->assertJsonPath('data.credit_balance', 100);

        $this->assertDatabaseCount('daily_step_logs', 1);
        $this->assertDatabaseCount('credit_transactions', 3);
    }

    public function test_today_weekly_balance_and_transactions_are_available(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fitness/sync-steps', [
                'provider' => 'apple_health',
                'date' => today()->toDateString(),
                'steps' => 2000,
                'goal_steps' => 10000,
            ])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/fitness/today')
            ->assertOk()
            ->assertJsonPath('data.steps', 2000);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/fitness/weekly')
            ->assertOk()
            ->assertJsonCount(7, 'data.chart');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/credits/balance')
            ->assertOk()
            ->assertJsonPath('data.balance', 10);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/credits/transactions')
            ->assertOk()
            ->assertJsonCount(1, 'data.transactions')
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_step_sync_validation_uses_api_error_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fitness/sync-steps', [])
            ->assertUnprocessable()
            ->assertExactJson([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => [
                    'provider' => ['The provider field is required.'],
                    'date' => ['The date field is required.'],
                    'steps' => ['The steps field is required.'],
                    'goal_steps' => ['The goal steps field is required.'],
                ],
            ]);
    }
}
