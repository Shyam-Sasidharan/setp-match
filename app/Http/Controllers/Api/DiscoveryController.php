<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StepMatch;
use App\Models\User;
use App\Services\FitnessStatsService;
use App\Services\MatchScoreService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscoveryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MatchScoreService $matchScoreService,
        private readonly FitnessStatsService $fitnessStatsService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 10), 1), 50);
        $excludedUserIds = $user->likes()->pluck('liked_user_id');
        $matchedUserIds = StepMatch::query()
            ->where('status', 'active')
            ->where(function ($query) use ($user): void {
                $query->where('user_one_id', $user->id)
                    ->orWhere('user_two_id', $user->id);
            })
            ->get(['user_one_id', 'user_two_id'])
            ->map(fn (StepMatch $match): int => $match->user_one_id === $user->id
                ? $match->user_two_id
                : $match->user_one_id);

        $candidates = User::query()
            ->whereKeyNot($user->id)
            ->whereNotIn('id', $excludedUserIds)
            ->whereNotIn('id', $matchedUserIds)
            ->whereHas('profile', fn ($query) => $query->where('profile_completed', true))
            ->with(['profile', 'interests'])
            ->paginate($perPage);

        $data = collect($candidates->items())
            ->map(fn (User $candidate): array => $this->userCard($user, $candidate))
            ->values()
            ->all();

        return $this->successResponse(
            'Discovery users retrieved successfully.',
            [
                'users' => $data,
                'pagination' => [
                    'current_page' => $candidates->currentPage(),
                    'last_page' => $candidates->lastPage(),
                    'per_page' => $candidates->perPage(),
                    'total' => $candidates->total(),
                ],
            ]
        );
    }

    public function matches(Request $request): JsonResponse
    {
        $user = $request->user();
        $matches = StepMatch::query()
            ->where('status', 'active')
            ->where(function ($query) use ($user): void {
                $query->where('user_one_id', $user->id)
                    ->orWhere('user_two_id', $user->id);
            })
            ->with(['userOne.profile', 'userOne.interests', 'userTwo.profile', 'userTwo.interests', 'conversation'])
            ->latest('matched_at')
            ->get()
            ->map(function (StepMatch $match) use ($user): array {
                $matchedUser = $match->user_one_id === $user->id
                    ? $match->userTwo
                    : $match->userOne;

                return [
                    'id' => $match->id,
                    'match_percent' => $match->match_percent,
                    'status' => $match->status,
                    'matched_at' => $match->matched_at,
                    'conversation_id' => $match->conversation?->id,
                    'user' => $this->userCard($user, $matchedUser, false),
                ];
            })
            ->values();

        return $this->successResponse(
            'Matches retrieved successfully.',
            ['matches' => $matches]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function userCard(User $currentUser, User $targetUser, bool $calculateScore = true): array
    {
        $targetUser->loadMissing(['profile', 'interests']);

        return [
            'id' => $targetUser->id,
            'name' => $targetUser->profile?->full_name ?? $targetUser->name,
            'age' => $targetUser->profile?->age,
            'profile_photo_url' => $targetUser->profilePhotoUrl(),
            'bio' => $targetUser->profile?->bio,
            'interests' => $targetUser->interests->map(fn ($interest): array => [
                'id' => $interest->id,
                'name' => $interest->name,
                'slug' => $interest->slug,
                'emoji' => $interest->emoji,
            ])->values(),
            'avg_steps' => $this->fitnessStatsService->averageDailySteps($targetUser, 7),
            'distance' => $this->distanceBetweenUsers($currentUser, $targetUser),
            'match_percent' => $calculateScore
                ? $this->matchScoreService->calculate($currentUser, $targetUser)
                : null,
            'is_verified' => $targetUser->email_verified_at !== null,
        ];
    }

    private function distanceBetweenUsers(User $currentUser, User $targetUser): ?float
    {
        $currentUser->loadMissing('profile');
        $currentProfile = $currentUser->profile;
        $targetProfile = $targetUser->profile;

        if (
            $currentProfile?->latitude === null ||
            $currentProfile->longitude === null ||
            $targetProfile?->latitude === null ||
            $targetProfile->longitude === null
        ) {
            return null;
        }

        $latitudeDelta = deg2rad((float) $targetProfile->latitude - (float) $currentProfile->latitude);
        $longitudeDelta = deg2rad((float) $targetProfile->longitude - (float) $currentProfile->longitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad((float) $currentProfile->latitude))
            * cos(deg2rad((float) $targetProfile->latitude))
            * sin($longitudeDelta / 2) ** 2;

        return round(6371 * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }
}
