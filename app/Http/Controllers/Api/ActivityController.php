<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Services\FitnessStatsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly FitnessStatsService $fitnessStatsService
    ) {}

    public function analytics(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = $this->fitnessStatsService->today($user);
        $weekly = $this->fitnessStatsService->weekly($user);
        $analytics = $this->fitnessStatsService->analytics($user);
        $badges = $user->badges()
            ->orderByPivot('awarded_at', 'desc')
            ->get()
            ->map(fn (Badge $badge): array => [
                'id' => $badge->id,
                'name' => $badge->name,
                'slug' => $badge->slug,
                'description' => $badge->description,
                'icon' => $badge->icon,
                'awarded_at' => $badge->pivot->awarded_at,
            ])
            ->values();

        return $this->successResponse(
            'Activity analytics retrieved successfully.',
            [
                'today_steps' => $today['steps'],
                'weekly_steps' => $weekly['steps'],
                'current_streak' => $analytics['streak'],
                'total_distance' => $analytics['distance'],
                'calories_burned' => $analytics['calories'],
                'avg_heart_rate' => $analytics['avg_heart_rate'],
                'active_hours' => round($analytics['active_minutes'] / 60, 2),
                'weekly_chart' => $analytics['weekly_chart'],
                'badges' => $badges,
            ]
        );
    }
}
