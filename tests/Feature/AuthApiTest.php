<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_a_sanctum_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Alex Walker',
            'email' => 'alex@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => 'android',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.user.email', 'alex@example.com')
            ->assertJsonPath('data.profile_completed', false)
            ->assertJsonPath('data.fitness_connected', false)
            ->assertJsonPath('data.credit_balance', 0)
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas('users', ['email' => 'alex@example.com']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_user_can_login_view_profile_and_logout(): void
    {
        User::factory()->create([
            'email' => 'alex@example.com',
            'password' => 'password123',
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'alex@example.com',
            'password' => 'password123',
        ])->assertOk();

        $token = $loginResponse->json('data.token');

        $this->withToken($token)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'alex@example.com');

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'alex@example.com']);

        $this->postJson('/api/login', [
            'email' => 'alex@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('status', false)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_protected_routes_require_a_bearer_token(): void
    {
        $this->getJson('/api/profile')
            ->assertUnauthorized()
            ->assertExactJson([
                'status' => false,
                'message' => 'Unauthenticated.',
                'errors' => [],
            ]);
    }

    public function test_registration_validation_uses_the_api_error_format(): void
    {
        $this->postJson('/api/register', [])
            ->assertUnprocessable()
            ->assertJsonPath('status', false)
            ->assertJsonStructure([
                'message',
                'errors' => ['name', 'email', 'password'],
            ]);
    }
}
