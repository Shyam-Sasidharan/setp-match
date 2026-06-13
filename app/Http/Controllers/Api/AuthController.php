<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        return $this->successResponse(
            'Registration successful.',
            $this->authenticationData(
                $user,
                $user->createToken($validated['device_name'] ?? 'mobile-app')->plainTextToken
            ),
            201
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse(
                'Invalid email or password.',
                ['credentials' => ['The provided credentials are incorrect.']],
                401
            );
        }

        return $this->successResponse(
            'Login successful.',
            $this->authenticationData(
                $user,
                $user->createToken($validated['device_name'] ?? 'mobile-app')->plainTextToken
            )
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->successResponse('Logout successful.');
    }

    public function profile(Request $request): JsonResponse
    {
        return $this->successResponse(
            'Profile retrieved successfully.',
            $this->userData($request->user())
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function authenticationData(User $user, string $token): array
    {
        return [
            'token' => $token,
            'token_type' => 'Bearer',
            ...$this->userData($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userData(User $user): array
    {
        $user->loadMissing(['profile', 'interests', 'creditWallet']);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'profile' => $user->profile ? [
                ...$user->profile->toArray(),
                'photo_url' => $user->profilePhotoUrl(),
                'interests' => $user->interests->map(fn ($interest): array => [
                    'id' => $interest->id,
                    'name' => $interest->name,
                    'slug' => $interest->slug,
                    'emoji' => $interest->emoji,
                ])->values(),
            ] : null,
            'credit_balance' => $user->creditBalance(),
            'profile_completed' => $user->isProfileCompleted(),
            'fitness_connected' => $user->isFitnessConnected(),
        ];
    }
}
