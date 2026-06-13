<?php

namespace App\Services;

use App\Models\User;

class MatchScoreService
{
    public function __construct(
        private readonly FitnessStatsService $fitnessStats
    ) {}

    public function calculate(User $currentUser, User $targetUser): int
    {
        $currentUser->load(['profile', 'interests']);
        $targetUser->load(['profile', 'interests']);

        $score =
            ($this->sharedInterestScore($currentUser, $targetUser) * 30) +
            ($this->stepSimilarityScore($currentUser, $targetUser) * 30) +
            ($this->distanceScore($currentUser, $targetUser) * 20) +
            ($this->streakScore($currentUser, $targetUser) * 10) +
            ($this->ageScore($currentUser, $targetUser) * 10);

        return (int) max(0, min(100, round($score)));
    }

    private function sharedInterestScore(User $currentUser, User $targetUser): float
    {
        $current = $currentUser->interests->pluck('id');
        $target = $targetUser->interests->pluck('id');
        $unionCount = $current->merge($target)->unique()->count();

        if ($unionCount === 0) {
            return 0;
        }

        return $current->intersect($target)->count() / $unionCount;
    }

    private function stepSimilarityScore(User $currentUser, User $targetUser): float
    {
        $currentAverage = $this->fitnessStats->averageDailySteps($currentUser);
        $targetAverage = $this->fitnessStats->averageDailySteps($targetUser);
        $largestAverage = max($currentAverage, $targetAverage);

        if ($largestAverage === 0) {
            return 0;
        }

        return 1 - (abs($currentAverage - $targetAverage) / $largestAverage);
    }

    private function distanceScore(User $currentUser, User $targetUser): float
    {
        $currentProfile = $currentUser->profile;
        $targetProfile = $targetUser->profile;

        if (
            $currentProfile?->latitude === null ||
            $currentProfile->longitude === null ||
            $targetProfile?->latitude === null ||
            $targetProfile->longitude === null
        ) {
            return 0;
        }

        $distanceKm = $this->distanceInKilometres(
            (float) $currentProfile->latitude,
            (float) $currentProfile->longitude,
            (float) $targetProfile->latitude,
            (float) $targetProfile->longitude
        );

        return max(0, 1 - ($distanceKm / 50));
    }

    private function streakScore(User $currentUser, User $targetUser): float
    {
        $currentStreak = $this->fitnessStats->currentStreak($currentUser);
        $targetStreak = $this->fitnessStats->currentStreak($targetUser);
        $largestStreak = max($currentStreak, $targetStreak);

        if ($largestStreak === 0) {
            return 0;
        }

        return 1 - (abs($currentStreak - $targetStreak) / $largestStreak);
    }

    private function ageScore(User $currentUser, User $targetUser): float
    {
        $currentAge = $currentUser->profile?->age;
        $targetAge = $targetUser->profile?->age;

        if ($currentAge === null || $targetAge === null) {
            return 0;
        }

        return max(0, 1 - (abs($currentAge - $targetAge) / 20));
    }

    private function distanceInKilometres(
        float $latitudeOne,
        float $longitudeOne,
        float $latitudeTwo,
        float $longitudeTwo
    ): float {
        $earthRadiusKm = 6371;
        $latitudeDelta = deg2rad($latitudeTwo - $latitudeOne);
        $longitudeDelta = deg2rad($longitudeTwo - $longitudeOne);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeOne))
            * cos(deg2rad($latitudeTwo))
            * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
