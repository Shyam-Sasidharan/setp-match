<?php

namespace Database\Seeders;

use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\DailyStepLog;
use App\Models\Interest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $interests = Interest::query()->pluck('id', 'name');

        foreach ($this->users() as $demoUser) {
            DB::transaction(function () use ($demoUser, $interests): void {
                $user = User::query()->updateOrCreate(
                    ['email' => $demoUser['email']],
                    [
                        'name' => $demoUser['name'],
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );

                $user->profile()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'full_name' => $demoUser['full_name'],
                        'age' => $demoUser['age'],
                        'gender' => 'male',
                        'bio' => $demoUser['bio'],
                        'walking_preferences' => $demoUser['walking_preferences'],
                        'latitude' => $demoUser['latitude'],
                        'longitude' => $demoUser['longitude'],
                        'city' => 'Kochi',
                        'state' => 'Kerala',
                        'country' => 'India',
                        'profile_completed' => true,
                        'fitness_connected' => true,
                        'daily_step_goal' => 10000,
                        'subscription_plan' => $demoUser['plan'],
                    ]
                );

                $interestIds = collect($demoUser['interests'])
                    ->map(fn (string $name): ?int => $interests->get($name))
                    ->filter()
                    ->values()
                    ->all();

                $user->interests()->sync($interestIds);
                $this->seedStepLogs($user, $demoUser['steps']);
                $this->seedCredits($user, $demoUser['credits']);
            });
        }
    }

    /**
     * @param  array<int, int>  $steps
     */
    private function seedStepLogs(User $user, array $steps): void
    {
        $startDate = CarbonImmutable::today()->subDays(6);

        foreach ($steps as $offset => $dailySteps) {
            $date = $startDate->addDays($offset);
            $attributes = [
                'provider' => 'google_fit',
                'steps' => $dailySteps,
                'goal_steps' => 10000,
                'distance_km' => round($dailySteps * 0.00075, 3),
                'calories' => round($dailySteps * 0.04, 2),
                'heart_rate' => 72 + ($offset % 7),
                'active_minutes' => (int) round($dailySteps / 120),
                'synced_at' => $date->endOfDay(),
            ];

            $log = DailyStepLog::query()
                ->where('user_id', $user->id)
                ->whereDate('log_date', $date)
                ->first();

            if ($log) {
                $log->update($attributes);
            } else {
                $user->dailyStepLogs()->create([
                    'log_date' => $date,
                    ...$attributes,
                ]);
            }
        }
    }

    /**
     * @param  array{earned: int, spent: int}  $credits
     */
    private function seedCredits(User $user, array $credits): void
    {
        $balance = $credits['earned'] - $credits['spent'];

        $wallet = CreditWallet::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => $balance,
                'lifetime_earned' => $credits['earned'],
                'lifetime_spent' => $credits['spent'],
            ]
        );

        CreditTransaction::query()->updateOrCreate(
            ['idempotency_key' => "demo-user:{$user->id}:welcome-credits"],
            [
                'user_id' => $user->id,
                'credit_wallet_id' => $wallet->id,
                'type' => 'earn',
                'reason' => 'demo_welcome',
                'amount' => $credits['earned'],
                'balance_after' => $credits['earned'],
                'description' => 'Demo account welcome credits.',
            ]
        );

        CreditTransaction::query()->updateOrCreate(
            ['idempotency_key' => "demo-user:{$user->id}:sample-spend"],
            [
                'user_id' => $user->id,
                'credit_wallet_id' => $wallet->id,
                'type' => 'spend',
                'reason' => 'demo_activity',
                'amount' => -$credits['spent'],
                'balance_after' => $balance,
                'description' => 'Demo account sample credit spend.',
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function users(): array
    {
        return [
            [
                'name' => 'shyam',
                'full_name' => 'shyam',
                'email' => 'shyam@example.com',
                'age' => 24,
                'plan' => 'gold',
                'bio' => 'Fitness enthusiast looking for active walking matches.',
                'walking_preferences' => 'Evening walks, trails, and weekend fitness challenges.',
                'latitude' => 9.9312,
                'longitude' => 76.2673,
                'interests' => ['Trails', 'Coffee', 'HIIT', 'Tech', 'Fitness'],
                'steps' => [8200, 9600, 10400, 11200, 7800, 12100, 10000],
                'credits' => ['earned' => 400, 'spent' => 25],
            ],
            [
                'name' => 'Jordan Vance',
                'full_name' => 'Jordan Vance',
                'email' => 'jordan@example.com',
                'age' => 25,
                'plan' => 'free',
                'bio' => 'Early morning runner who loves canyon trails and coffee after walks.',
                'walking_preferences' => 'Morning runs, trail walks, and competitive step challenges.',
                'latitude' => 9.9412,
                'longitude' => 76.2773,
                'interests' => ['Trails', 'Coffee', 'HIIT', 'Running'],
                'steps' => [13200, 14500, 13800, 15100, 14200, 14700, 13900],
                'credits' => ['earned' => 300, 'spent' => 20],
            ],
            [
                'name' => 'Marcus Chen',
                'full_name' => 'Marcus Chen',
                'email' => 'marcus@example.com',
                'age' => 28,
                'plan' => 'free',
                'bio' => 'Tech professional who unwinds with sunset walks and fitness goals.',
                'walking_preferences' => 'Sunset walks, city routes, and steady weekly progress.',
                'latitude' => 9.9512,
                'longitude' => 76.2873,
                'interests' => ['Fitness', 'Tech', 'Sunset'],
                'steps' => [10900, 12100, 11500, 12400, 11800, 12200, 11700],
                'credits' => ['earned' => 250, 'spent' => 25],
            ],
        ];
    }
}
