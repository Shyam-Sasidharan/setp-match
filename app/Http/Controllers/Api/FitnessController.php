<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fitness\FitnessConnectRequest;
use App\Http\Requests\Fitness\SyncStepsRequest;
use App\Models\DailyStepLog;
use App\Models\FitnessConnection;
use App\Services\CreditService;
use App\Services\FitnessStatsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FitnessController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CreditService $creditService,
        private readonly FitnessStatsService $fitnessStatsService
    ) {}

    public function connect(FitnessConnectRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $connection = DB::transaction(function () use ($user, $validated): FitnessConnection {
            $connection = $user->fitnessConnections()->updateOrCreate(
                ['provider' => $validated['provider']],
                [
                    ...Arr::except($validated, ['provider']),
                    'status' => 'connected',
                    'connected_at' => now(),
                ]
            );

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                ['fitness_connected' => true]
            );

            return $connection;
        });

        return $this->successResponse(
            'Fitness provider connected successfully.',
            [
                'connection' => $this->connectionData($connection),
                'fitness_connected' => true,
            ]
        );
    }

    public function syncSteps(SyncStepsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $result = DB::transaction(function () use ($user, $validated): array {
            $log = DailyStepLog::query()
                ->where('user_id', $user->id)
                ->whereDate('log_date', $validated['date'])
                ->lockForUpdate()
                ->first();

            $oldSteps = $log?->steps ?? 0;
            $logData = [
                'provider' => $validated['provider'],
                'log_date' => $validated['date'],
                'steps' => $validated['steps'],
                'goal_steps' => $validated['goal_steps'],
                'distance_km' => $validated['distance_km'] ?? 0,
                'calories' => $validated['calories'] ?? 0,
                'heart_rate' => $validated['heart_rate'] ?? null,
                'active_minutes' => $validated['active_minutes'] ?? 0,
                'source_data' => $validated['source_data'] ?? null,
                'synced_at' => now(),
            ];

            if ($log) {
                $log->update($logData);
            } else {
                $log = $user->dailyStepLogs()->create($logData);
            }

            $creditsEarned = $this->creditService->convertStepsToCredits(
                $user,
                $log,
                $oldSteps
            );

            $connection = $user->fitnessConnections()->updateOrCreate(
                ['provider' => $validated['provider']],
                [
                    'status' => 'connected',
                    'connected_at' => now(),
                    'last_synced_at' => now(),
                ]
            );

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                ['fitness_connected' => true]
            );

            return [
                'log' => $log->fresh(),
                'connection' => $connection,
                'credits_earned' => $creditsEarned,
            ];
        });

        return $this->successResponse(
            'Step data synced successfully.',
            [
                'daily_step_log' => $result['log'],
                'today' => $this->fitnessStatsService->today($user),
                'credits_earned' => $result['credits_earned'],
                'credit_balance' => $this->creditService->getBalance($user),
                'last_synced_at' => $result['connection']->last_synced_at,
            ]
        );
    }

    public function today(Request $request): JsonResponse
    {
        return $this->successResponse(
            "Today's fitness statistics retrieved successfully.",
            $this->fitnessStatsService->today($request->user())
        );
    }

    public function weekly(Request $request): JsonResponse
    {
        return $this->successResponse(
            'Weekly fitness statistics retrieved successfully.',
            $this->fitnessStatsService->weekly($request->user())
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionData(FitnessConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'provider' => $connection->provider,
            'provider_user_id' => $connection->provider_user_id,
            'status' => $connection->status,
            'connected_at' => $connection->connected_at,
            'last_synced_at' => $connection->last_synced_at,
        ];
    }
}
