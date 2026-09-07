<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\PasskeyChallengeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('two-factor-challenge/passkey', PasskeyChallengeController::class)
        ->name('two-factor.passkey');

    Route::get('invitation/{token}', [InvitationController::class, 'show'])
        ->name('invitation.show');
    Route::post('invitation/{token}', [InvitationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('invitation.store');
});

Route::middleware('auth')->group(function () {
    Route::get('two-factor/setup', TwoFactorSetupController::class)
        ->name('two-factor.setup');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
