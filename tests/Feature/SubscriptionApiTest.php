<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_defaults_to_free_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.current_plan', 'free')
            ->assertJsonPath('data.membership_title', 'Free Member')
            ->assertJsonPath('data.expiry', null)
            ->assertJsonFragment(['limited discovery']);
    }

    public function test_user_can_change_demo_subscription_and_profile_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/change-demo', [
                'plan' => 'gold',
            ])
            ->assertOk()
            ->assertJsonPath('data.current_plan', 'gold')
            ->assertJsonPath('data.membership_title', 'Gold Member')
            ->assertJsonPath('data.subscription.provider', 'demo')
            ->assertJsonFragment(['priority matching']);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'subscription_plan' => 'gold',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'provider' => 'demo',
            'plan' => 'gold',
            'status' => 'active',
        ]);
    }

    public function test_repeated_demo_changes_update_one_subscription_row(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/change-demo', ['plan' => 'gold'])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/change-demo', ['plan' => 'premium'])
            ->assertOk()
            ->assertJsonPath('data.current_plan', 'premium')
            ->assertJsonPath('data.membership_title', 'Premium Member')
            ->assertJsonFragment(['extra super likes']);

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan' => 'premium',
        ]);
    }

    public function test_free_demo_plan_has_no_expiry_and_validation_uses_api_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/change-demo', ['plan' => 'free'])
            ->assertOk()
            ->assertJsonPath('data.current_plan', 'free')
            ->assertJsonPath('data.expiry', null);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/change-demo', ['plan' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Validation failed');
    }

    public function test_subscription_routes_require_authentication(): void
    {
        $this->getJson('/api/subscription')->assertUnauthorized();
        $this->postJson('/api/subscription/change-demo', ['plan' => 'gold'])
            ->assertUnauthorized();
    }
}
