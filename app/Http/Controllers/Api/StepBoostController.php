<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyStepLog;
use App\Models\StepBoost;
use App\Models\User;
use App\Services\CreditService;
use App\Services\FitnessStatsService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StepBoostController extends Controller
{
    use ApiResponse;

    private const BOOST_STEPS = 1200;

    private const DAILY_LIMIT = 3;

    public function __construct(
        private readonly CreditService $creditService,
        private readonly FitnessStatsService $fitnessStatsService
    ) {}

    public function boost(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'steps' => ['required', 'integer', Rule::in([self::BOOST_STEPS])],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $user = $request->user();

        try {
            $boost = DB::transaction(function () use ($user): StepBoost {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                $boostCount = StepBoost::query()
                    ->where('user_id', $user->id)
                    ->whereDate('boost_date', today())
                    ->count();

                if ($boostCount >= self::DAILY_LIMIT) {
                    throw new DomainException('Daily step boost limit reached.');
                }

                $log = DailyStepLog::query()
                    ->where('user_id', $user->id)
                    ->whereDate('log_date', today())
                    ->lockForUpdate()
                    ->first();

                if (! $log) {
                    $log = $user->dailyStepLogs()->create([
                        'provider' => 'step_boost',
                        'log_date' => today(),
                        'steps' => 0,
                        'goal_steps' => $user->profile?->daily_step_goal ?? 10000,
                        'synced_at' => now(),
                    ]);
                }

                $boost = $user->stepBoosts()->create([
                    'daily_step_log_id' => $log->id,
                    'boost_date' => today(),
                    'boost_steps' => self::BOOST_STEPS,
                    'credits_spent' => CreditService::STEP_BOOST_COST,
                    'status' => 'applied',
                    'applied_at' => now(),
                ]);

                $this->creditService->spend(
                    $user,
                    CreditService::STEP_BOOST_COST,
                    'step_boost',
                    'Purchased a 1,200-step boost.',
                    $boost
                );

                $log->increment('steps', self::BOOST_STEPS);

                return $boost;
            });
        } catch (DomainException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                ['boost' => [$exception->getMessage()]],
                422
            );
        }

        return $this->successResponse(
            'Step boost applied successfully.',
            [
                'boost' => $boost->fresh(),
                'boosts_used_today' => $user->stepBoosts()
                    ->whereDate('boost_date', today())
                    ->count(),
                'boosts_remaining_today' => max(
                    0,
                    self::DAILY_LIMIT - $user->stepBoosts()
                        ->whereDate('boost_date', today())
                        ->count()
                ),
                'credit_balance' => $this->creditService->getBalance($user),
                'today' => $this->fitnessStatsService->today($user),
            ]
        );
    }
}
