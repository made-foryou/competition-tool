<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\PasskeyChallengeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\CompetitionParticipantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MatchDayController;
use App\Http\Controllers\MatchDayFieldController;
use App\Http\Controllers\Participant\AvailabilityController;
use App\Http\Controllers\Participant\CompetitionDashboardController;
use App\Http\Controllers\Participant\CompetitionLoginController;
use App\Http\Controllers\Participant\CompetitionRegistrationController;
use App\Http\Controllers\Participant\Settings\PasswordController as ParticipantPasswordController;
use App\Http\Controllers\Participant\Settings\ProfileController as ParticipantProfileController;
use App\Http\Controllers\Participant\Settings\SecurityController as ParticipantSecurityController;
use App\Http\Controllers\Participant\Settings\SettingsController as ParticipantSettingsController;
use App\Http\Middleware\EnsureCompetitionIsVisible;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserParticipatesInCompetition;
use Illuminate\Auth\Middleware\RequirePassword;
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
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('competitions', CompetitionController::class)->except(['show']);

    Route::post('competitions/{competition}/participants', [CompetitionParticipantController::class, 'store'])
        ->name('competitions.participants.store');
    Route::delete('competitions/{competition}/participants/{user}', [CompetitionParticipantController::class, 'destroy'])
        ->name('competitions.participants.destroy');

    Route::prefix('competitions/{competition}')
        ->name('competitions.')
        ->scopeBindings()
        ->group(function () {
            Route::post('match-days', [MatchDayController::class, 'store'])
                ->name('match-days.store');
            Route::get('match-days/{matchDay}/edit', [MatchDayController::class, 'edit'])
                ->name('match-days.edit');
            Route::put('match-days/{matchDay}', [MatchDayController::class, 'update'])
                ->name('match-days.update');
            Route::delete('match-days/{matchDay}', [MatchDayController::class, 'destroy'])
                ->name('match-days.destroy');

            Route::post('match-days/{matchDay}/fields', [MatchDayFieldController::class, 'store'])
                ->name('match-days.fields.store');
            Route::delete('match-days/{matchDay}/fields/{field}', [MatchDayFieldController::class, 'destroy'])
                ->name('match-days.fields.destroy');
        });
});

require __DIR__.'/settings.php';

Route::prefix('{competition:slug}')
    ->middleware(EnsureCompetitionIsVisible::class)
    ->group(function () {
        Route::get('login', CompetitionLoginController::class)
            ->name('competition.login');

        Route::get('register', [CompetitionRegistrationController::class, 'show'])
            ->name('competition.register.show');
        Route::post('register', [CompetitionRegistrationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('competition.register.store');

        Route::middleware(EnsureUserParticipatesInCompetition::class)->group(function () {
            Route::get('/', CompetitionDashboardController::class)
                ->name('competition.dashboard');

            Route::get('availability', [AvailabilityController::class, 'edit'])
                ->name('competition.availability.edit');
            Route::put('availability', [AvailabilityController::class, 'update'])
                ->name('competition.availability.update');

            Route::prefix('settings')->name('competition.settings.')->group(function () {
                Route::get('/', ParticipantSettingsController::class)->name('index');

                Route::get('profile', [ParticipantProfileController::class, 'edit'])->name('profile.edit');
                Route::patch('profile', [ParticipantProfileController::class, 'update'])->name('profile.update');

                Route::get('password', [ParticipantPasswordController::class, 'edit'])->name('password.edit');
                Route::put('password', [ParticipantPasswordController::class, 'update'])
                    ->middleware('throttle:6,1')
                    ->name('password.update');

                Route::get('security', ParticipantSecurityController::class)
                    ->middleware(RequirePassword::class)
                    ->name('security.edit');
            });
        });
    });
