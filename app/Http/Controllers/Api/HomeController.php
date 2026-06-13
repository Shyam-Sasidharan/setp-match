<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CreditService;
use App\Services\FitnessStatsService;
use App\Services\MatchScoreService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly FitnessStatsService $fitnessStatsService,
        private readonly CreditService $creditService,
        private readonly MatchScoreService $matchScoreService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('profile');
        $today = $this->fitnessStatsService->today($user);
        $weekly = $this->fitnessStatsService->weekly($user);
        $creditBalance = $this->creditService->getBalance($user);

        return $this->successResponse(
            'Home dashboard retrieved successfully.',
            [
                'subscription_plan' => $user->profile?->subscription_plan ?? 'free',
                'today_steps' => $today['steps'],
                'daily_goal' => $today['goal'],
                'progress_percent' => $today['progress_percent'],
                'daily_streak' => $today['streak'],
                'credit_balance' => $creditBalance,
                'mover_score' => $creditBalance,
                'weekly_progress' => $weekly,
                'featured_challenge' => [
                    'title' => 'Complete 10,000 Steps Today',
                    'reward_credits' => CreditService::DAILY_GOAL_BONUS,
                    'boost_steps' => 1200,
                    'boost_credit_cost' => CreditService::STEP_BOOST_COST,
                ],
                'nearby_walkers' => $this->nearbyWalkers($user),
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function nearbyWalkers(User $user): array
    {
        return User::query()
            ->whereKeyNot($user->id)
            ->whereHas('profile', fn ($query) => $query->where('profile_completed', true))
            ->with('profile')
            ->limit(5)
            ->get()
            ->map(function (User $walker) use ($user): array {
                return [
                    'id' => $walker->id,
                    'name' => $walker->profile?->full_name ?? $walker->name,
                    'age' => $walker->profile?->age,
                    'photo_url' => $walker->profilePhotoUrl(),
                    'average_daily_steps' => $this->fitnessStatsService->averageDailySteps($walker, 7),
                    'match_percent' => $this->matchScoreService->calculate($user, $walker),
                    'city' => $walker->profile?->city,
                ];
            })
            ->values()
            ->all();
    }
}
