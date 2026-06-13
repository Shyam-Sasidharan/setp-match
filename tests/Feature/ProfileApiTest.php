<?php

namespace Tests\Feature;

use App\Models\Interest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_setup_and_update_profile(): void
    {
        $user = User::factory()->create();
        $interests = Interest::query()->insert([
            [
                'name' => 'Trails',
                'slug' => 'trails',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Coffee',
                'slug' => 'coffee',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertTrue($interests);
        $interestIds = Interest::query()->pluck('id')->all();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/setup', [
                'full_name' => 'Alex Walker',
                'age' => 24,
                'gender' => 'male',
                'bio' => 'Morning walker',
                'interests' => $interestIds,
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.profile.profile_completed', true)
            ->assertJsonPath('data.credit_balance', 0);

        $this->assertDatabaseHas('credit_wallets', ['user_id' => $user->id]);
        $this->assertDatabaseCount('interest_user', 2);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'bio' => 'Evening walker',
                'interests' => [$interestIds[0]],
            ])
            ->assertOk()
            ->assertJsonPath('data.profile.bio', 'Evening walker');

        $this->assertDatabaseCount('interest_user', 1);
    }

    public function test_user_can_upload_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/profile/photo', [
                'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
            ], ['Accept' => 'application/json']);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['profile_photo', 'photo_url']]);

        Storage::disk('public')->assertExists($response->json('data.profile_photo'));
    }

    public function test_interests_endpoint_returns_only_active_interests(): void
    {
        $user = User::factory()->create();
        Interest::query()->create(['name' => 'Trails', 'slug' => 'trails', 'is_active' => true]);
        Interest::query()->create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/interests')
            ->assertOk()
            ->assertJsonCount(1, 'data.interests')
            ->assertJsonPath('data.interests.0.name', 'Trails');
    }

    public function test_login_returns_real_profile_and_wallet_data(): void
    {
        $user = User::factory()->create([
            'email' => 'alex@example.com',
            'password' => 'password123',
        ]);
        $user->profile()->create([
            'full_name' => 'Alex Walker',
            'age' => 24,
            'gender' => 'male',
            'profile_completed' => true,
            'fitness_connected' => true,
        ]);
        $user->creditWallet()->create(['balance' => 125]);

        $this->postJson('/api/login', [
            'email' => 'alex@example.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('data.profile.full_name', 'Alex Walker')
            ->assertJsonPath('data.credit_balance', 125)
            ->assertJsonPath('data.profile_completed', true)
            ->assertJsonPath('data.fitness_connected', true);
    }
}
