<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\PasskeyChallengeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\CompetitionAvailabilityExportController;
use App\Http\Controllers\CompetitionAvailabilityReminderController;
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\CompetitionInvitationController;
use App\Http\Controllers\CompetitionParticipantController;
use App\Http\Controllers\CompetitionParticipantRoleController;
use App\Http\Controllers\CompetitionScheduleController;
use App\Http\Controllers\CompetitionSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MatchDayController;
use App\Http\Controllers\MatchDayFieldController;
use App\Http\Controllers\MatchPinController;
use App\Http\Controllers\MatchScheduleController;
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
});

// Bewust buiten de guest-groep: op het moment van versturen is niet bekend of
// de ontvanger straks ingelogd is, dus één url die zich naar de sessie
// gedraagt is de enige variant die in beide gevallen klopt. De controller
// vangt de ingelogde bezoeker zelf af.
Route::get('invitation/{token}', [InvitationController::class, 'show'])
    ->name('invitation.show');
Route::post('invitation/{token}', [InvitationController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('invitation.store');
Route::post('invitation/{token}/decline', [InvitationController::class, 'decline'])
    ->middleware('throttle:6,1')
    ->name('invitation.decline');

Route::middleware('auth')->group(function () {
    Route::get('two-factor/setup', TwoFactorSetupController::class)
        ->name('two-factor.setup');

    Route::inertia('no-competition', 'participant/no-competition')->name('competition.none');
});

Route::middleware(['auth', 'verified', EnsureUserIsAdmin::class])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('competitions', CompetitionController::class)->except(['show']);

    Route::prefix('competitions/{competition}')
        ->name('competitions.')
        ->scopeBindings()
        ->group(function () {
            Route::post('participants', [CompetitionParticipantController::class, 'store'])
                ->name('participants.store');

            // Vertelt het toevoegformulier of een ingetypt e-mailadres al een
            // account heeft, zodat de beheerder de juiste keuze te zien
            // krijgt. Adviserend: de server beslist alsnog zelf, want het
            // antwoord kan tussen opzoeken en verzenden verouderen.
            Route::get('participants/lookup', [CompetitionParticipantController::class, 'lookup'])
                ->middleware('throttle:60,1')
                ->name('participants.lookup');

            // {participant} in plaats van {user}: de scoped binding zoekt de
            // relatie op via de parameternaam (participant -> participants()),
            // zodat alleen daadwerkelijke deelnemers van deze competitie
            // resolven en andere users een 404 opleveren.
            Route::delete('participants/{participant}', [CompetitionParticipantController::class, 'destroy'])
                ->name('participants.destroy');

            // De rol is globaal (niet per competitie), maar deze route zit
            // bewust binnen de competitie-scope: hier ziet de beheerder de
            // deelnemer, en {participant} levert via dezelfde scoped binding
            // gratis een 404 op voor een user die geen deelnemer is.
            Route::patch('participants/{participant}/role', CompetitionParticipantRoleController::class)
                ->name('participants.role');

            // {invitation} resolvet via de scoped binding op invitations(),
            // zodat een uitnodiging van een andere competitie automatisch een
            // 404 oplevert.
            Route::delete('invitations/{invitation}', [CompetitionInvitationController::class, 'destroy'])
                ->name('invitations.destroy');
            // Throttle omdat dit endpoint synchroon mail verstuurt: zonder
            // limiet levert doorklikken een stapel mails op bij de genodigde,
            // waarvan alleen de laatste link nog werkt.
            Route::post('invitations/{invitation}/resend', [CompetitionInvitationController::class, 'resend'])
                ->middleware('throttle:6,1')
                ->name('invitations.resend');

            // Throttle omdat dit endpoint een ronde herinneringsmails in gang
            // zet: het 24-uursvenster op de competitie dekt herhaald verzenden
            // al af, maar de limiet houdt ook het aantal mislukte pogingen (en
            // dus queries) bij doorklikken in toom.
            Route::post('availability/reminders', CompetitionAvailabilityReminderController::class)
                ->middleware('throttle:6,1')
                ->name('availability.reminders');

            Route::get('availability/export', CompetitionAvailabilityExportController::class)
                ->name('availability.export');

            Route::put('settings', CompetitionSettingsController::class)
                ->name('settings.update');

            // Throttle omdat dit endpoint het hele schema synchroon uitrekent
            // en in één schrijfronde wegzet: de limiet houdt doorklikken in
            // toom, net als bij de herinneringen.
            Route::post('schedule', [CompetitionScheduleController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('schedule.store');

            // Zelfde throttle als aanvullen: opnieuw plannen rekent het hele
            // schema synchroon uit en laat eerst alles los, dus doorklikken is
            // hier nog duurder.
            Route::post('schedule/rebuild', [CompetitionScheduleController::class, 'rebuild'])
                ->middleware('throttle:6,1')
                ->name('schedule.rebuild');

            // {match} resolvet via de scoped binding op Competition::matches(),
            // zodat een wedstrijd van een andere competitie een 404 geeft --
            // zelfde mechaniek als {participant} en {invitation} hierboven.
            Route::put('matches/{match}/schedule', [MatchScheduleController::class, 'update'])
                ->name('matches.schedule.update');
            Route::post('matches/{match}/pin', [MatchPinController::class, 'store'])
                ->name('matches.pin.store');
            Route::delete('matches/{match}/pin', [MatchPinController::class, 'destroy'])
                ->name('matches.pin.destroy');

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
