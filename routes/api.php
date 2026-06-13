<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\FitnessController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StepBoostController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SwipeController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/profile/setup', [ProfileController::class, 'setup']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto']);
    Route::get('/interests', [ProfileController::class, 'interests']);

    Route::post('/fitness/connect', [FitnessController::class, 'connect']);
    Route::post('/fitness/sync-steps', [FitnessController::class, 'syncSteps']);
    Route::get('/fitness/today', [FitnessController::class, 'today']);
    Route::get('/fitness/weekly', [FitnessController::class, 'weekly']);

    Route::get('/credits/balance', [CreditController::class, 'balance']);
    Route::get('/credits/transactions', [CreditController::class, 'transactions']);

    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/activity/analytics', [ActivityController::class, 'analytics']);
    Route::post('/steps/boost', [StepBoostController::class, 'boost']);

    Route::get('/discovery', [DiscoveryController::class, 'index']);
    Route::post('/swipe', [SwipeController::class, 'swipe']);
    Route::get('/matches', [DiscoveryController::class, 'matches']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/{notification}/dismiss', [NotificationController::class, 'dismiss']);

    Route::get('/chats', [ChatController::class, 'index']);
    Route::get('/chats/{conversation}/messages', [ChatController::class, 'messages']);
    Route::post('/chats/{conversation}/messages', [ChatController::class, 'sendMessage']);
    Route::post('/chats/{conversation}/audio', [ChatController::class, 'uploadAudio']);
    Route::post('/chats/{conversation}/read', [ChatController::class, 'markRead']);

    Route::post('/challenges/invite', [ChallengeController::class, 'invite']);
    Route::post('/challenges/{challenge}/accept', [ChallengeController::class, 'accept']);
    Route::post('/challenges/{challenge}/reject', [ChallengeController::class, 'reject']);

    Route::get('/subscription', [SubscriptionController::class, 'index']);
    Route::post('/subscription/change-demo', [SubscriptionController::class, 'changeDemo']);
});
