<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\SwipeActionRequest;
use App\Models\Conversation;
use App\Models\StepMatch;
use App\Models\User;
use App\Models\UserLike;
use App\Services\CreditService;
use App\Services\MatchScoreService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SwipeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CreditService $creditService,
        private readonly MatchScoreService $matchScoreService
    ) {}

    public function swipe(SwipeActionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $targetUser = User::query()->findOrFail($validated['target_user_id']);

        if ($user->is($targetUser)) {
            return $this->errorResponse(
                'You cannot swipe on yourself.',
                ['target_user_id' => ['You cannot swipe on yourself.']],
                422
            );
        }

        try {
            $result = DB::transaction(function () use ($user, $targetUser, $validated): array {
                User::query()
                    ->whereIn('id', [$user->id, $targetUser->id])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($user->likes()->where('liked_user_id', $targetUser->id)->exists()) {
                    throw new DomainException('You have already swiped on this user.');
                }

                $creditsSpent = $validated['action'] === 'super_like'
                    ? CreditService::SUPER_LIKE_COST
                    : 0;

                $like = $user->likes()->create([
                    'liked_user_id' => $targetUser->id,
                    'action' => $validated['action'],
                    'credits_spent' => $creditsSpent,
                    'acted_at' => now(),
                ]);

                if ($creditsSpent > 0) {
                    $this->creditService->spend(
                        $user,
                        $creditsSpent,
                        'super_like',
                        'Sent a super like.',
                        $like
                    );
                }

                $isPositiveAction = in_array($validated['action'], ['like', 'super_like'], true);
                $reciprocalLike = $isPositiveAction && UserLike::query()
                    ->where('user_id', $targetUser->id)
                    ->where('liked_user_id', $user->id)
                    ->whereIn('action', ['like', 'super_like'])
                    ->exists();

                if (! $reciprocalLike) {
                    return [
                        'like' => $like,
                        'is_match' => false,
                        'match' => null,
                        'conversation' => null,
                    ];
                }

                [$userOneId, $userTwoId] = $user->id < $targetUser->id
                    ? [$user->id, $targetUser->id]
                    : [$targetUser->id, $user->id];

                $match = StepMatch::query()->firstOrCreate(
                    [
                        'user_one_id' => $userOneId,
                        'user_two_id' => $userTwoId,
                    ],
                    [
                        'match_percent' => $this->matchScoreService->calculate($user, $targetUser),
                        'status' => 'active',
                        'matched_at' => now(),
                    ]
                );

                $conversation = Conversation::query()->firstOrCreate(
                    ['step_match_id' => $match->id],
                    ['type' => 'direct']
                );

                $conversation->participants()->syncWithoutDetaching([
                    $user->id => ['joined_at' => now()],
                    $targetUser->id => ['joined_at' => now()],
                ]);

                return [
                    'like' => $like,
                    'is_match' => true,
                    'match' => $match,
                    'conversation' => $conversation,
                ];
            });
        } catch (DomainException $exception) {
            $statusCode = $exception->getMessage() === 'You have already swiped on this user.'
                ? 409
                : 422;

            return $this->errorResponse(
                $exception->getMessage(),
                ['swipe' => [$exception->getMessage()]],
                $statusCode
            );
        }

        return $this->successResponse(
            $result['is_match'] ? "It's a match!" : 'Swipe recorded successfully.',
            [
                'action' => $result['like']->action,
                'target_user_id' => $targetUser->id,
                'credits_spent' => $result['like']->credits_spent,
                'credit_balance' => $this->creditService->getBalance($user),
                'is_match' => $result['is_match'],
                'match' => $result['match'] ? [
                    'id' => $result['match']->id,
                    'match_percent' => $result['match']->match_percent,
                    'matched_at' => $result['match']->matched_at,
                    'conversation_id' => $result['conversation']->id,
                    'matched_user' => [
                        'id' => $targetUser->id,
                        'name' => $targetUser->profile?->full_name ?? $targetUser->name,
                        'profile_photo_url' => $targetUser->profilePhotoUrl(),
                    ],
                ] : null,
            ],
            201
        );
    }
}
