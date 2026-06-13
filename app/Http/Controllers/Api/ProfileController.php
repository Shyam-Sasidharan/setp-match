<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ProfileSetupRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadProfilePhotoRequest;
use App\Models\CreditWallet;
use App\Models\Interest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponse;

    public function setup(ProfileSetupRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($user, $validated): void {
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    ...Arr::except($validated, ['interests']),
                    'profile_completed' => true,
                ]
            );

            $user->interests()->sync($validated['interests']);

            CreditWallet::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'balance' => 0,
                    'lifetime_earned' => 0,
                    'lifetime_spent' => 0,
                ]
            );
        });

        return $this->successResponse(
            'Profile setup completed successfully.',
            $this->profileData($user->fresh())
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($user, $validated): void {
            $profileData = Arr::except($validated, ['interests']);

            if ($profileData !== []) {
                $user->profile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData
                );
            }

            if (array_key_exists('interests', $validated)) {
                $user->interests()->sync($validated['interests']);
            }
        });

        return $this->successResponse(
            'Profile updated successfully.',
            $this->profileData($user->fresh())
        );
    }

    public function uploadPhoto(UploadProfilePhotoRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);
        $oldPhoto = $profile->profile_photo;
        $path = $request->file('profile_photo')->store('profile_photos', 'public');

        $profile->update(['profile_photo' => $path]);

        if ($oldPhoto && $oldPhoto !== $path) {
            Storage::disk('public')->delete($oldPhoto);
        }

        $user->unsetRelation('profile');

        return $this->successResponse(
            'Profile photo uploaded successfully.',
            [
                'profile_photo' => $path,
                'photo_url' => $user->profilePhotoUrl(),
            ]
        );
    }

    public function interests(): JsonResponse
    {
        $interests = Interest::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'emoji']);

        return $this->successResponse(
            'Interests retrieved successfully.',
            ['interests' => $interests]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function profileData(User $user): array
    {
        $user->load(['profile', 'interests', 'creditWallet']);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'profile' => $user->profile ? [
                ...$user->profile->toArray(),
                'photo_url' => $user->profilePhotoUrl(),
            ] : null,
            'interests' => $user->interests->map(fn (Interest $interest): array => [
                'id' => $interest->id,
                'name' => $interest->name,
                'slug' => $interest->slug,
                'emoji' => $interest->emoji,
            ])->values(),
            'credit_balance' => $user->creditBalance(),
            'profile_completed' => $user->isProfileCompleted(),
            'fitness_connected' => $user->isFitnessConnected(),
        ];
    }
}
