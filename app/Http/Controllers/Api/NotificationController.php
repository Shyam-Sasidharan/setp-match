<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);
        $query = $user->notifications()->whereNull('dismissed_at');
        $unreadCount = (clone $query)->whereNull('read_at')->count();
        $notifications = $query->latest()->paginate($perPage);

        return $this->successResponse(
            'Notifications retrieved successfully.',
            [
                'notifications' => collect($notifications->items())
                    ->map(fn (AppNotification $notification): array => $this->notificationData($notification))
                    ->values(),
                'unread_count' => $unreadCount,
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
            ]
        );
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        if (! $this->ownsNotification($request, $notification)) {
            return $this->errorResponse(
                'You are not authorized to access this notification.',
                ['notification' => ['This notification does not belong to you.']],
                403
            );
        }

        $notification->markAsRead();

        return $this->successResponse(
            'Notification marked as read.',
            ['notification' => $this->notificationData($notification->fresh())]
        );
    }

    public function dismiss(Request $request, AppNotification $notification): JsonResponse
    {
        if (! $this->ownsNotification($request, $notification)) {
            return $this->errorResponse(
                'You are not authorized to access this notification.',
                ['notification' => ['This notification does not belong to you.']],
                403
            );
        }

        $notification->dismiss();

        return $this->successResponse(
            'Notification dismissed successfully.',
            ['notification_id' => $notification->id]
        );
    }

    private function ownsNotification(Request $request, AppNotification $notification): bool
    {
        return $notification->user_id === $request->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationData(AppNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->body,
            'data' => $notification->data,
            'is_read' => $notification->read_at !== null,
            'created_at' => $notification->created_at,
            'time_ago' => $notification->created_at->diffForHumans(),
        ];
    }
}
