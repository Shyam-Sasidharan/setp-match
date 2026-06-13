<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_non_dismissed_notifications_with_unread_count(): void
    {
        $user = User::factory()->create();
        AppNotification::createFor(
            $user,
            'match',
            'New Match',
            'You matched with Jordan.',
            ['match_id' => 10]
        );
        AppNotification::createFor(
            $user,
            'credits',
            'Credits Earned',
            'You earned 50 credits.'
        )->markAsRead();
        AppNotification::createFor(
            $user,
            'system',
            'Hidden',
            'This notification was dismissed.'
        )->dismiss();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?per_page=10')
            ->assertOk()
            ->assertJsonCount(2, 'data.notifications')
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonStructure([
                'data' => [
                    'notifications' => [[
                        'id',
                        'type',
                        'title',
                        'message',
                        'data',
                        'is_read',
                        'created_at',
                        'time_ago',
                    ]],
                ],
            ]);
    }

    public function test_user_can_mark_own_notification_as_read_and_dismiss_it(): void
    {
        $user = User::factory()->create();
        $notification = AppNotification::createFor(
            $user,
            'badge',
            'Badge Earned',
            'You earned the Early Bird badge.'
        );

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.notification.is_read', true);

        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/dismiss")
            ->assertOk()
            ->assertJsonPath('data.notification_id', $notification->id);

        $this->assertNotNull($notification->fresh()->dismissed_at);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data.notifications');
    }

    public function test_user_cannot_access_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = AppNotification::createFor(
            $owner,
            'challenge',
            'Challenge Invite',
            'You received a walking challenge.'
        );

        $this->actingAs($otherUser, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertForbidden()
            ->assertJsonPath('status', false);

        $this->actingAs($otherUser, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/dismiss")
            ->assertForbidden()
            ->assertJsonPath('status', false);

        $this->assertNull($notification->fresh()->read_at);
        $this->assertNull($notification->fresh()->dismissed_at);
    }

    public function test_notification_helper_supports_only_defined_types(): void
    {
        $user = User::factory()->create();

        foreach (AppNotification::TYPES as $type) {
            AppNotification::createFor(
                $user,
                $type,
                ucfirst($type),
                "A {$type} notification."
            );
        }

        $this->assertDatabaseCount('app_notifications', 6);

        $this->expectException(InvalidArgumentException::class);

        AppNotification::createFor(
            $user,
            'unsupported',
            'Unsupported',
            'This should fail.'
        );
    }
}
