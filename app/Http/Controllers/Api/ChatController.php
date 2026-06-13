<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversations = $user->conversations()
            ->wherePivotNull('left_at')
            ->whereHas('stepMatch', fn ($query) => $query->where('status', 'active'))
            ->with([
                'participants.profile',
                'stepMatch',
            ])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function (Conversation $conversation) use ($user): array {
                $otherUser = $conversation->participants->firstWhere('id', '!=', $user->id);
                $lastMessage = $conversation->messages()->latest()->first();
                $unreadCount = $conversation->messages()
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at')
                    ->count();

                return [
                    'id' => $conversation->id,
                    'match_id' => $conversation->step_match_id,
                    'match_percent' => $conversation->stepMatch?->match_percent,
                    'participant' => $otherUser ? $this->participantData($otherUser) : null,
                    'last_message' => $lastMessage ? $this->messageData($lastMessage) : null,
                    'unread_count' => $unreadCount,
                    'last_message_at' => $conversation->last_message_at,
                ];
            })
            ->values();

        return $this->successResponse(
            'Chats retrieved successfully.',
            ['chats' => $conversations]
        );
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        if (! $this->canAccess($request, $conversation)) {
            return $this->forbiddenResponse();
        }

        $perPage = min(max((int) $request->integer('per_page', 30), 1), 100);
        $messages = $conversation->messages()
            ->with(['sender.profile', 'walkingChallenge'])
            ->orderBy('id')
            ->paginate($perPage);

        return $this->successResponse(
            'Messages retrieved successfully.',
            [
                'messages' => collect($messages->items())
                    ->map(fn (Message $message): array => $this->messageData($message))
                    ->values(),
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ],
            ]
        );
    }

    public function sendMessage(
        SendMessageRequest $request,
        Conversation $conversation
    ): JsonResponse {
        if (! $this->canAccess($request, $conversation)) {
            return $this->forbiddenResponse();
        }

        $message = DB::transaction(function () use ($request, $conversation): Message {
            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'type' => 'text',
                'body' => $request->validated('message'),
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            return $message;
        });

        return $this->successResponse(
            'Message sent successfully.',
            ['message' => $this->messageData($message->load('sender.profile'))],
            201
        );
    }

    public function uploadAudio(Request $request, Conversation $conversation): JsonResponse
    {
        if (! $this->canAccess($request, $conversation)) {
            return $this->forbiddenResponse();
        }

        $validator = Validator::make($request->all(), [
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,aac', 'max:10240'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:3600'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $path = $request->file('audio')->store('chat_audio', 'public');

        try {
            $message = DB::transaction(function () use ($request, $conversation, $path): Message {
                $message = $conversation->messages()->create([
                    'sender_id' => $request->user()->id,
                    'type' => 'audio',
                    'audio_path' => $path,
                    'audio_duration_seconds' => $request->integer('duration_seconds') ?: null,
                ]);

                $conversation->update(['last_message_at' => $message->created_at]);

                return $message;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        return $this->successResponse(
            'Audio message sent successfully.',
            ['message' => $this->messageData($message->load('sender.profile'))],
            201
        );
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        if (! $this->canAccess($request, $conversation)) {
            return $this->forbiddenResponse();
        }

        $readAt = now();

        DB::transaction(function () use ($request, $conversation, $readAt): void {
            $conversation->participantRecords()
                ->where('user_id', $request->user()->id)
                ->update(['last_read_at' => $readAt]);

            $conversation->messages()
                ->where('sender_id', '!=', $request->user()->id)
                ->whereNull('read_at')
                ->update(['read_at' => $readAt]);
        });

        return $this->successResponse(
            'Conversation marked as read.',
            [
                'conversation_id' => $conversation->id,
                'read_at' => $readAt,
            ]
        );
    }

    private function canAccess(Request $request, Conversation $conversation): bool
    {
        return $conversation->stepMatch()
            ->where('status', 'active')
            ->exists()
            && $conversation->participantRecords()
                ->where('user_id', $request->user()->id)
                ->whereNull('left_at')
                ->exists();
    }

    private function forbiddenResponse(): JsonResponse
    {
        return $this->errorResponse(
            'You are not authorized to access this conversation.',
            ['conversation' => ['You are not an active participant in this matched conversation.']],
            403
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function messageData(Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender' => $message->sender ? $this->participantData($message->sender) : null,
            'message_type' => $message->type,
            'message' => $message->body,
            'media_url' => $message->audio_path
                ? Storage::disk('public')->url($message->audio_path)
                : null,
            'audio_duration_seconds' => $message->audio_duration_seconds,
            'challenge' => $message->walkingChallenge,
            'metadata' => $message->metadata,
            'is_read' => $message->read_at !== null,
            'created_at' => $message->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function participantData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->profile?->full_name ?? $user->name,
            'profile_photo_url' => $user->profilePhotoUrl(),
        ];
    }
}
