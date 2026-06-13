<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ChallengeInviteRequest;
use App\Models\AppNotification;
use App\Models\Conversation;
use App\Models\StepMatch;
use App\Models\User;
use App\Models\WalkingChallenge;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChallengeController extends Controller
{
    use ApiResponse;

    public function invite(ChallengeInviteRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $opponent = User::query()->findOrFail($validated['opponent_id']);
        $match = $this->activeMatchBetween($user, $opponent);

        if (! $match) {
            return $this->errorResponse(
                'Walking challenges can only be sent to matched users.',
                ['opponent_id' => ['The selected user is not an active match.']],
                422
            );
        }

        $result = DB::transaction(function () use ($validated, $user, $opponent, $match): array {
            $conversation = Conversation::query()->firstOrCreate(
                ['step_match_id' => $match->id],
                ['type' => 'direct']
            );

            $conversation->participants()->syncWithoutDetaching([
                $user->id => ['joined_at' => now()],
                $opponent->id => ['joined_at' => now()],
            ]);

            $challenge = WalkingChallenge::query()->create([
                'conversation_id' => $conversation->id,
                'step_match_id' => $match->id,
                'inviter_id' => $user->id,
                'invitee_id' => $opponent->id,
                'title' => $validated['title']
                    ?? $this->defaultTitle($validated['challenge_type'], $validated['target_value']),
                'description' => $validated['description'] ?? null,
                'metric' => $validated['challenge_type'],
                'target_value' => (int) ceil($validated['target_value']),
                'challenge_date' => $validated['start_date'],
                'status' => 'pending',
            ]);

            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'walking_challenge_id' => $challenge->id,
                'type' => 'challenge',
                'body' => "{$user->name} invited you to a walking challenge.",
                'metadata' => [
                    'challenge_type' => $validated['challenge_type'],
                    'target_value' => $validated['target_value'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'status' => 'pending',
                ],
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            AppNotification::createFor(
                $opponent,
                'challenge',
                'Walking Challenge Invitation',
                "{$user->name} invited you to a {$validated['challenge_type']} challenge.",
                [
                    'challenge_id' => $challenge->id,
                    'conversation_id' => $conversation->id,
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                ],
                $user
            );

            return compact('challenge', 'conversation', 'message');
        });

        return $this->successResponse(
            'Walking challenge invitation sent successfully.',
            [
                'challenge' => $this->challengeData(
                    $result['challenge'],
                    $validated['start_date'],
                    $validated['end_date']
                ),
                'conversation_id' => $result['conversation']->id,
                'message_id' => $result['message']->id,
            ],
            201
        );
    }

    public function accept(Request $request, WalkingChallenge $challenge): JsonResponse
    {
        return $this->respond($request, $challenge, 'accepted');
    }

    public function reject(Request $request, WalkingChallenge $challenge): JsonResponse
    {
        return $this->respond($request, $challenge, 'rejected');
    }

    private function respond(
        Request $request,
        WalkingChallenge $challenge,
        string $status
    ): JsonResponse {
        try {
            $challenge = DB::transaction(function () use ($request, $challenge, $status): WalkingChallenge {
                $lockedChallenge = WalkingChallenge::query()
                    ->lockForUpdate()
                    ->findOrFail($challenge->id);

                if ($lockedChallenge->invitee_id !== $request->user()->id) {
                    throw new DomainException('not_opponent');
                }

                if ($lockedChallenge->status !== 'pending') {
                    throw new DomainException('already_responded');
                }

                $lockedChallenge->update([
                    'status' => $status,
                    'responded_at' => now(),
                ]);

                $conversation = $lockedChallenge->conversation;

                if ($conversation) {
                    $message = $conversation->messages()->create([
                        'sender_id' => $request->user()->id,
                        'walking_challenge_id' => $lockedChallenge->id,
                        'type' => 'system',
                        'body' => "Challenge {$status}.",
                        'metadata' => [
                            'challenge_id' => $lockedChallenge->id,
                            'status' => $status,
                        ],
                    ]);

                    $conversation->update(['last_message_at' => $message->created_at]);
                }

                return $lockedChallenge;
            });
        } catch (DomainException $exception) {
            if ($exception->getMessage() === 'not_opponent') {
                return $this->errorResponse(
                    'Only the invited opponent can respond to this challenge.',
                    ['challenge' => ['You are not the invited opponent.']],
                    403
                );
            }

            return $this->errorResponse(
                'This challenge has already been responded to.',
                ['challenge' => ['Only pending challenges can be accepted or rejected.']],
                409
            );
        }

        return $this->successResponse(
            "Challenge {$status} successfully.",
            ['challenge' => $this->challengeData($challenge)]
        );
    }

    private function activeMatchBetween(User $user, User $opponent): ?StepMatch
    {
        [$userOneId, $userTwoId] = $user->id < $opponent->id
            ? [$user->id, $opponent->id]
            : [$opponent->id, $user->id];

        return StepMatch::query()
            ->where('user_one_id', $userOneId)
            ->where('user_two_id', $userTwoId)
            ->where('status', 'active')
            ->first();
    }

    private function defaultTitle(string $type, int|float $target): string
    {
        $unit = $type === 'steps' ? 'steps' : 'km';

        return "Complete {$target} {$unit} Together";
    }

    /**
     * @return array<string, mixed>
     */
    private function challengeData(
        WalkingChallenge $challenge,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        return [
            'id' => $challenge->id,
            'conversation_id' => $challenge->conversation_id,
            'match_id' => $challenge->step_match_id,
            'creator_id' => $challenge->inviter_id,
            'opponent_id' => $challenge->invitee_id,
            'title' => $challenge->title,
            'description' => $challenge->description,
            'challenge_type' => $challenge->metric,
            'target_value' => $challenge->target_value,
            'start_date' => $startDate ?? $challenge->challenge_date?->toDateString(),
            'end_date' => $endDate,
            'status' => $challenge->status,
            'responded_at' => $challenge->responded_at,
            'completed_at' => $challenge->completed_at,
            'created_at' => $challenge->created_at,
        ];
    }
}
