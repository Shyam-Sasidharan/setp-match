<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\ChangeSubscriptionRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    use ApiResponse;

    private const PLANS = [
        'free' => [
            'membership_title' => 'Free Member',
            'features' => [
                'limited discovery',
                'normal likes',
                'limited boosts',
                'no premium badge',
            ],
        ],
        'gold' => [
            'membership_title' => 'Gold Member',
            'features' => [
                'more discovery',
                'more boosts',
                'Gold Member badge',
                'priority matching',
            ],
        ],
        'premium' => [
            'membership_title' => 'Premium Member',
            'features' => [
                'unlimited discovery',
                'premium badge',
                'priority matching',
                'extra super likes',
            ],
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        return $this->successResponse(
            'Subscription retrieved successfully.',
            $this->subscriptionData($request->user())
        );
    }

    public function changeDemo(ChangeSubscriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($user, $validated): void {
            $plan = $validated['plan'];
            $expiresAt = $plan === 'free' ? null : now()->addMonth();

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                ['subscription_plan' => $plan]
            );

            $subscription = $user->subscriptions()
                ->where('provider', 'demo')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $attributes = [
                'provider' => 'demo',
                'provider_subscription_id' => $validated['provider_subscription_id']
                    ?? "demo-{$user->id}",
                'plan' => $plan,
                'status' => 'active',
                'starts_at' => now(),
                'current_period_ends_at' => $expiresAt,
                'cancelled_at' => null,
                'ends_at' => $expiresAt,
                'metadata' => [
                    'development_only' => true,
                    'requested_provider' => $validated['provider'] ?? null,
                ],
            ];

            if ($subscription) {
                $subscription->update($attributes);
            } else {
                $user->subscriptions()->create($attributes);
            }
        });

        return $this->successResponse(
            'Demo subscription changed successfully.',
            $this->subscriptionData($user->fresh())
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionData(User $user): array
    {
        $user->load('profile');
        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->latest('id')
            ->first();
        $plan = $user->profile?->subscription_plan
            ?? $subscription?->plan
            ?? 'free';
        $planDetails = self::PLANS[$plan] ?? self::PLANS['free'];

        return [
            'current_plan' => $plan,
            'membership_title' => $planDetails['membership_title'],
            'features' => $planDetails['features'],
            'expiry' => $subscription?->current_period_ends_at ?? $subscription?->ends_at,
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'provider' => $subscription->provider,
                'provider_subscription_id' => $subscription->provider_subscription_id,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at,
                'trial_ends_at' => $subscription->trial_ends_at,
                'current_period_ends_at' => $subscription->current_period_ends_at,
                'ends_at' => $subscription->ends_at,
            ] : null,
        ];
    }
}
