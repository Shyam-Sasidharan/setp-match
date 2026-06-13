<?php

namespace App\Services;

use App\Models\DailyStepLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FitnessStatsService
{
    public function today(User $user): array
    {
        $log = $user->dailyStepLogs()
            ->whereDate('log_date', today())
            ->first();

        $steps = $log?->steps ?? 0;
        $goal = $log?->goal_steps ?? $user->profile?->daily_step_goal ?? 10000;

        return [
            'steps' => $steps,
            'goal' => $goal,
            'progress_percent' => $this->progressPercent($steps, $goal),
            'streak' => $this->currentStreak($user),
            'distance' => (float) ($log?->distance_km ?? 0),
            'calories' => (float) ($log?->calories ?? 0),
            'avg_heart_rate' => $log?->heart_rate,
            'active_minutes' => $log?->active_minutes ?? 0,
        ];
    }

    public function weekly(User $user): array
    {
        $end = CarbonImmutable::today();
        $start = $end->subDays(6);
        $logs = $user->dailyStepLogs()
            ->whereDate('log_date', '>=', $start)
            ->whereDate('log_date', '<=', $end)
            ->get()
            ->keyBy(fn (DailyStepLog $log): string => $log->log_date->toDateString());

        $chart = collect(range(0, 6))->map(function (int $offset) use ($start, $logs, $user): array {
            $date = $start->addDays($offset);
            $log = $logs->get($date->toDateString());
            $steps = $log?->steps ?? 0;
            $goal = $log?->goal_steps ?? $user->profile?->daily_step_goal ?? 10000;

            return [
                'date' => $date->toDateString(),
                'day' => $date->format('D'),
                'steps' => $steps,
                'goal' => $goal,
                'progress_percent' => $this->progressPercent($steps, $goal),
            ];
        });

        return [
            'steps' => $chart->sum('steps'),
            'goal' => $chart->sum('goal'),
            'progress_percent' => $this->progressPercent($chart->sum('steps'), $chart->sum('goal')),
            'streak' => $this->currentStreak($user),
            'distance' => round((float) $logs->sum('distance_km'), 3),
            'calories' => round((float) $logs->sum('calories'), 2),
            'avg_heart_rate' => $this->averageHeartRate($logs),
            'active_minutes' => (int) $logs->sum('active_minutes'),
            'chart' => $chart->values()->all(),
        ];
    }

    public function monthlySteps(User $user): int
    {
        return (int) $user->dailyStepLogs()
            ->whereDate('log_date', '>=', now()->startOfMonth())
            ->whereDate('log_date', '<=', now()->endOfMonth())
            ->sum('steps');
    }

    public function lifetimeSteps(User $user): int
    {
        return (int) $user->dailyStepLogs()->sum('steps');
    }

    public function currentStreak(User $user): int
    {
        $logs = $user->dailyStepLogs()
            ->whereDate('log_date', '<=', today())
            ->orderByDesc('log_date')
            ->get(['log_date', 'steps', 'goal_steps'])
            ->keyBy(fn (DailyStepLog $log): string => $log->log_date->toDateString());

        $cursor = CarbonImmutable::today();
        $todayLog = $logs->get($cursor->toDateString());

        if (! $todayLog || $todayLog->steps < $todayLog->goal_steps) {
            $cursor = $cursor->subDay();
        }

        $streak = 0;

        while ($log = $logs->get($cursor->toDateString())) {
            if ($log->steps < $log->goal_steps) {
                break;
            }

            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    public function averageDailySteps(User $user, int $days = 30): int
    {
        if ($days <= 0) {
            return 0;
        }

        $steps = $user->dailyStepLogs()
            ->whereDate('log_date', '>=', today()->subDays($days - 1))
            ->whereDate('log_date', '<=', today())
            ->sum('steps');

        return (int) round($steps / $days);
    }

    public function analytics(User $user): array
    {
        $weekly = $this->weekly($user);
        $lifetimeLogs = $user->dailyStepLogs()->get();

        return [
            'steps' => $this->lifetimeSteps($user),
            'goal' => $user->profile?->daily_step_goal ?? 10000,
            'progress_percent' => $weekly['progress_percent'],
            'streak' => $this->currentStreak($user),
            'distance' => round((float) $lifetimeLogs->sum('distance_km'), 3),
            'calories' => round((float) $lifetimeLogs->sum('calories'), 2),
            'avg_heart_rate' => $this->averageHeartRate($lifetimeLogs),
            'active_minutes' => (int) $lifetimeLogs->sum('active_minutes'),
            'weekly_chart' => $weekly['chart'],
            'monthly_steps' => $this->monthlySteps($user),
            'average_daily_steps' => $this->averageDailySteps($user),
        ];
    }

    private function progressPercent(int $steps, int $goal): int
    {
        if ($goal <= 0) {
            return 0;
        }

        return (int) min(100, round(($steps / $goal) * 100));
    }

    /**
     * @param  Collection<int|string, DailyStepLog>  $logs
     */
    private function averageHeartRate(Collection $logs): ?int
    {
        $heartRates = $logs->pluck('heart_rate')->filter(
            fn (mixed $heartRate): bool => $heartRate !== null
        );

        return $heartRates->isEmpty() ? null : (int) round($heartRates->average());
    }
}
