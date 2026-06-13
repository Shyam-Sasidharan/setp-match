<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\StepMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatChallengeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_participants_can_list_chat_send_messages_and_mark_read(): void
    {
        [$user, $opponent, $conversation] = $this->matchedConversation();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonCount(1, 'data.chats')
            ->assertJsonPath('data.chats.0.participant.id', $opponent->id);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/chats/{$conversation->id}/messages", [
                'message' => 'Ready for a walk?',
            ])
            ->assertCreated()
            ->assertJsonPath('data.message.message_type', 'text')
            ->assertJsonPath('data.message.message', 'Ready for a walk?');

        $messageId = $response->json('data.message.id');

        $this->actingAs($opponent, 'sanctum')
            ->getJson("/api/chats/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.messages.0.id', $messageId);

        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/chats/{$conversation->id}/read")
            ->assertOk();

        $this->assertDatabaseMissing('messages', [
            'id' => $messageId,
            'read_at' => null,
        ]);
    }

    public function test_non_participant_and_inactive_match_cannot_chat(): void
    {
        [$user, , $conversation, $match] = $this->matchedConversation();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/chats/{$conversation->id}/messages")
            ->assertForbidden();

        $match->update(['status' => 'inactive']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chats/{$conversation->id}/messages", [
                'message' => 'This should not send.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_participant_can_upload_audio_and_receive_public_url(): void
    {
        Storage::fake('public');
        [$user, , $conversation] = $this->matchedConversation();

        $response = $this->actingAs($user, 'sanctum')
            ->post("/api/chats/{$conversation->id}/audio", [
                'audio' => UploadedFile::fake()->create('voice.mp3', 500, 'audio/mpeg'),
                'duration_seconds' => 14,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.message.message_type', 'audio')
            ->assertJsonPath('data.message.audio_duration_seconds', 14);

        $path = $this->app['db']->table('messages')->value('audio_path');

        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($response->json('data.message.media_url'));
    }

    public function test_matched_user_can_invite_and_opponent_can_accept_challenge(): void
    {
        [$user, $opponent, $conversation] = $this->matchedConversation();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/challenges/invite', [
                'opponent_id' => $opponent->id,
                'challenge_type' => 'steps',
                'target_value' => 10000,
                'start_date' => today()->addDay()->toDateString(),
                'end_date' => today()->addDays(2)->toDateString(),
                'title' => 'Weekend 10k',
            ])
            ->assertCreated()
            ->assertJsonPath('data.challenge.status', 'pending')
            ->assertJsonPath('data.conversation_id', $conversation->id);

        $challengeId = $response->json('data.challenge.id');

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $opponent->id,
            'type' => 'challenge',
        ]);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'type' => 'challenge',
        ]);

        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/challenges/{$challengeId}/accept")
            ->assertOk()
            ->assertJsonPath('data.challenge.status', 'accepted');

        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/challenges/{$challengeId}/reject")
            ->assertStatus(409);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'type' => 'system',
            'body' => 'Challenge accepted.',
        ]);
    }

    public function test_challenge_requires_match_and_only_opponent_can_respond(): void
    {
        [$user, $opponent] = $this->matchedConversation();
        $stranger = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/challenges/invite', [
                'opponent_id' => $stranger->id,
                'challenge_type' => 'distance',
                'target_value' => 5,
                'start_date' => today()->addDay()->toDateString(),
                'end_date' => today()->addDay()->toDateString(),
            ])
            ->assertUnprocessable();

        $challengeResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/challenges/invite', [
                'opponent_id' => $opponent->id,
                'challenge_type' => 'distance',
                'target_value' => 5,
                'start_date' => today()->addDay()->toDateString(),
                'end_date' => today()->addDay()->toDateString(),
            ])
            ->assertCreated();

        $challengeId = $challengeResponse->json('data.challenge.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/challenges/{$challengeId}/accept")
            ->assertForbidden();
    }

    /**
     * @return array{User, User, Conversation, StepMatch}
     */
    private function matchedConversation(): array
    {
        $user = User::factory()->create(['name' => 'Alex']);
        $opponent = User::factory()->create(['name' => 'Jordan']);
        $match = StepMatch::query()->create([
            'user_one_id' => min($user->id, $opponent->id),
            'user_two_id' => max($user->id, $opponent->id),
            'match_percent' => 90,
            'status' => 'active',
        ]);
        $conversation = Conversation::query()->create([
            'step_match_id' => $match->id,
            'type' => 'direct',
        ]);
        $conversation->participants()->attach([
            $user->id => ['joined_at' => now()],
            $opponent->id => ['joined_at' => now()],
        ]);

        return [$user, $opponent, $conversation, $match];
    }
}
