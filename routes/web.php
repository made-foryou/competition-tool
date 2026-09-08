<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\PasskeyChallengeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\Participant\CompetitionDashboardController;
use App\Http\Controllers\Participant\CompetitionLoginController;
use App\Http\Middleware\EnsureCompetitionIsVisible;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserParticipatesInCompetition;
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

    Route::inertia('no-competition', 'participant/no-competition')->name('competition.none');
});

Route::middleware(['auth', 'verified', EnsureUserIsAdmin::class])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('competitions', CompetitionController::class)->except(['show']);
});

require __DIR__.'/settings.php';

Route::prefix('{competition:slug}')
    ->middleware(EnsureCompetitionIsVisible::class)
    ->group(function () {
        Route::get('login', CompetitionLoginController::class)
            ->name('competition.login');

        Route::get('/', CompetitionDashboardController::class)
            ->middleware(EnsureUserParticipatesInCompetition::class)
            ->name('competition.dashboard');
    });
