<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\DailyStepLog;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditService
{
    public const CREDITS_PER_THOUSAND_STEPS = 5;

    public const DAILY_GOAL_BONUS = 50;

    public const SUPER_LIKE_COST = 25;

    public const STEP_BOOST_COST = 20;

    public function getBalance(User $user): int
    {
        return DB::transaction(function () use ($user): int {
            return $this->walletFor($user)->balance;
        });
    }

    public function earn(
        User $user,
        int $amount,
        string $source,
        string $description,
        mixed $reference = null
    ): CreditTransaction {
        $this->ensurePositiveAmount($amount);

        return DB::transaction(function () use ($user, $amount, $source, $description, $reference) {
            $wallet = $this->lockedWalletFor($user);

            return $this->recordTransaction(
                user: $user,
                wallet: $wallet,
                amount: $amount,
                source: $source,
                description: $description,
                reference: $reference,
                type: 'earn'
            );
        });
    }

    public function spend(
        User $user,
        int $amount,
        string $source,
        string $description,
        mixed $reference = null
    ): CreditTransaction {
        $this->ensurePositiveAmount($amount);

        return DB::transaction(function () use ($user, $amount, $source, $description, $reference) {
            $wallet = $this->lockedWalletFor($user);

            if ($wallet->balance < $amount) {
                throw new DomainException('Insufficient credit balance.');
            }

            return $this->recordTransaction(
                user: $user,
                wallet: $wallet,
                amount: -$amount,
                source: $source,
                description: $description,
                reference: $reference,
                type: 'spend'
            );
        });
    }

    public function convertStepsToCredits(User $user, DailyStepLog $log, int $oldSteps = 0): int
    {
        if ($log->user_id !== $user->id) {
            throw new InvalidArgumentException('The step log does not belong to the user.');
        }

        return DB::transaction(function () use ($user, $log, $oldSteps): int {
            $lockedLog = DailyStepLog::query()->lockForUpdate()->findOrFail($log->id);
            $wallet = $this->lockedWalletFor($user);
            $currentSteps = max(0, $lockedLog->steps);
            $previousSteps = min(max(0, $oldSteps), $currentSteps);

            $creditsAtCurrentSteps = intdiv($currentSteps, 1000) * self::CREDITS_PER_THOUSAND_STEPS;
            $creditsAtPreviousSteps = intdiv($previousSteps, 1000) * self::CREDITS_PER_THOUSAND_STEPS;
            $newThresholdCredits = $creditsAtCurrentSteps - $creditsAtPreviousSteps;
            $unawardedCredits = max(0, $creditsAtCurrentSteps - $lockedLog->step_credits_awarded);
            $stepCredits = min($newThresholdCredits, $unawardedCredits);
            $totalCredits = 0;

            if ($stepCredits > 0) {
                $this->recordTransaction(
                    user: $user,
                    wallet: $wallet,
                    amount: $stepCredits,
                    source: 'steps',
                    description: "Credits earned for {$currentSteps} daily steps.",
                    reference: $lockedLog,
                    type: 'earn',
                    idempotencyKey: "daily-step-log:{$lockedLog->id}:steps:{$creditsAtCurrentSteps}"
                );

                $lockedLog->update([
                    'step_credits_awarded' => $creditsAtCurrentSteps,
                ]);
                $totalCredits += $stepCredits;
            }

            $goal = max(1, $lockedLog->goal_steps);

            if ($currentSteps >= $goal && ! $lockedLog->goal_bonus_awarded) {
                $this->recordTransaction(
                    user: $user,
                    wallet: $wallet,
                    amount: self::DAILY_GOAL_BONUS,
                    source: 'daily_goal',
                    description: "Daily step goal completed for {$lockedLog->log_date->toDateString()}.",
                    reference: $lockedLog,
                    type: 'earn',
                    idempotencyKey: "daily-step-log:{$lockedLog->id}:goal-bonus"
                );

                $lockedLog->update(['goal_bonus_awarded' => true]);
                $totalCredits += self::DAILY_GOAL_BONUS;
            }

            return $totalCredits;
        });
    }

    private function walletFor(User $user): CreditWallet
    {
        return CreditWallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'lifetime_earned' => 0, 'lifetime_spent' => 0]
        );
    }

    private function lockedWalletFor(User $user): CreditWallet
    {
        $this->walletFor($user);

        return CreditWallet::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function recordTransaction(
        User $user,
        CreditWallet $wallet,
        int $amount,
        string $source,
        string $description,
        mixed $reference,
        string $type,
        ?string $idempotencyKey = null
    ): CreditTransaction {
        $newBalance = $wallet->balance + $amount;

        if ($newBalance < 0) {
            throw new DomainException('Insufficient credit balance.');
        }

        $wallet->balance = $newBalance;

        if ($amount > 0) {
            $wallet->lifetime_earned += $amount;
        } else {
            $wallet->lifetime_spent += abs($amount);
        }

        $wallet->save();

        $transaction = new CreditTransaction([
            'user_id' => $user->id,
            'type' => $type,
            'reason' => $source,
            'amount' => $amount,
            'balance_after' => $newBalance,
            'idempotency_key' => $idempotencyKey,
            'description' => $description,
        ]);

        if ($reference instanceof Model) {
            $transaction->reference()->associate($reference);
        }

        $wallet->transactions()->save($transaction);

        return $transaction;
    }

    private function ensurePositiveAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit amount must be greater than zero.');
        }
    }
}
