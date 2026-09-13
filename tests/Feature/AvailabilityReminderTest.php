<?php

use App\Actions\Competitions\SendAvailabilityReminders;
use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\User;
use App\Notifications\AvailabilityReminderNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());
});

test('a reminder only goes to participants who have not submitted their availability', function () {
    Notification::fake();

    $competition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);
    $pending = User::factory()->participant()->create();
    $submitted = User::factory()->participant()->create();
    $competition->participants()->attach($pending);
    $competition->participants()->attach($submitted, ['availability_submitted_at' => now()]);

    $this->post(route('competitions.availability.reminders', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __('Reminder sent to :count participant.', ['count' => 1]),
        ]);

    Notification::assertSentToTimes($pending, AvailabilityReminderNotification::class, 1);
    Notification::assertNotSentTo($submitted, AvailabilityReminderNotification::class);
    Notification::assertCount(1);
});

test('a reminder is not sent to participants of another competition', function () {
    Notification::fake();

    $competition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);
    $participant = User::factory()->participant()->create();
    $competition->participants()->attach($participant);

    $otherCompetition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $otherCompetition]);
    $otherParticipant = User::factory()->participant()->create();
    $otherCompetition->participants()->attach($otherParticipant);

    $this->post(route('competitions.availability.reminders', $competition))
        ->assertRedirect();

    Notification::assertSentTo($participant, AvailabilityReminderNotification::class);
    Notification::assertNotSentTo($otherParticipant, AvailabilityReminderNotification::class);
    Notification::assertCount(1);
});

test('a sent reminder records the moment it was sent', function () {
    Notification::fake();
    $this->travelTo('2026-01-05 09:00:00');

    $competition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);
    $competition->participants()->attach(User::factory()->participant()->create());

    $this->post(route('competitions.availability.reminders', $competition))
        ->assertRedirect();

    expect($competition->refresh()->availability_reminder_sent_at?->toDateTimeString())
        ->toBe('2026-01-05 09:00:00');
});

test('a second round within twenty-four hours sends nothing and keeps the first timestamp', function () {
    Notification::fake();
    $this->travelTo('2026-01-05 09:00:00');

    $competition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);
    $competition->participants()->attach(User::factory()->participant()->create());

    $this->post(route('competitions.availability.reminders', $competition));

    $this->travel(23)->hours();
    Notification::fake();

    $this->post(route('competitions.availability.reminders', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => __('A reminder was already sent in the past 24 hours.'),
        ]);

    Notification::assertNothingSent();
    expect($competition->refresh()->availability_reminder_sent_at?->toDateTimeString())
        ->toBe('2026-01-05 09:00:00');
});

test('a new round is allowed once the twenty-four hour window has passed', function () {
    Notification::fake();
    $this->travelTo('2026-01-05 09:00:00');

    $competition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);
    $pending = User::factory()->participant()->create();
    $competition->participants()->attach($pending);

    $this->post(route('competitions.availability.reminders', $competition));

    $this->travel(25)->hours();
    Notification::fake();

    $this->post(route('competitions.availability.reminders', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __('Reminder sent to :count participant.', ['count' => 1]),
        ]);

    Notification::assertSentToTimes($pending, AvailabilityReminderNotification::class, 1);
    expect($competition->refresh()->availability_reminder_sent_at?->toDateTimeString())
        ->toBe('2026-01-06 10:00:00');
});

test('a reminder is refused for a competition that is not active', function (string $state) {
    Notification::fake();

    $competition = Competition::factory()->{$state}()->create();
    $competition->participants()->attach(User::factory()->participant()->create());

    $this->post(route('competitions.availability.reminders', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => __('Reminders can only be sent for an active competition.'),
        ]);

    Notification::assertNothingSent();
    expect($competition->refresh()->availability_reminder_sent_at)->toBeNull();
})->with(['draft', 'finished']);

test('a round without pending participants sends nothing and leaves the window open', function () {
    Notification::fake();

    $competition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);
    $competition->participants()->attach(
        User::factory()->participant()->create(),
        ['availability_submitted_at' => now()],
    );

    $this->post(route('competitions.availability.reminders', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => __('Everyone has already filled in their availability.'),
        ]);

    Notification::assertNothingSent();

    // Het openhouden van het venster is het punt van deze test: een ronde die
    // niemand bereikte mag de beheerder geen etmaal blokkeren.
    expect($competition->refresh()->availability_reminder_sent_at)->toBeNull()
        ->and($competition->availabilityReminderWindowIsOpen())->toBeTrue();
});

test('a participant cannot send reminders', function () {
    Notification::fake();

    $competition = Competition::factory()->create();
    $participant = User::factory()->participant()->create();
    $competition->participants()->attach($participant);

    $this->actingAs($participant)
        ->post(route('competitions.availability.reminders', $competition))
        ->assertForbidden();

    Notification::assertNothingSent();
    expect($competition->refresh()->availability_reminder_sent_at)->toBeNull();
});

test('guests are redirected to the login page', function () {
    Notification::fake();

    $competition = Competition::factory()->create();
    $competition->participants()->attach(User::factory()->participant()->create());

    auth()->logout();

    $this->post(route('competitions.availability.reminders', $competition))
        ->assertRedirect(route('login'));

    Notification::assertNothingSent();
});

test('the reminder mail links to the availability page of the competition', function () {
    $competition = Competition::factory()->create();
    $participant = User::factory()->participant()->create();
    $competition->participants()->attach($participant);

    $mail = (new AvailabilityReminderNotification($competition))->toMail($participant);

    expect($mail->subject)->toBe(__('Reminder: fill in your availability for :competition', [
        'competition' => $competition->name,
    ]))
        ->and($mail->actionText)->toBe(__('Fill in availability'))
        ->and($mail->actionUrl)->toBe(route('competition.availability.edit', $competition));
});

test('a reminder to more than one participant uses the plural message', function () {
    Notification::fake();

    $competition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);
    $competition->participants()->attach(User::factory()->participant()->count(2)->create());

    $this->post(route('competitions.availability.reminders', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __('Reminder sent to :count participants.', ['count' => 2]),
        ]);

    Notification::assertCount(2);
});

test('the window is still closed at exactly twenty-four hours and opens one second later', function () {
    Notification::fake();
    $this->travelTo('2026-01-05 09:00:00');

    $competition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);
    $competition->participants()->attach(User::factory()->participant()->create());

    $this->post(route('competitions.availability.reminders', $competition));

    $this->travel(24)->hours();
    Notification::fake();

    $this->post(route('competitions.availability.reminders', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => __('A reminder was already sent in the past 24 hours.'),
        ]);

    Notification::assertNothingSent();

    $this->travel(1)->second();

    $this->post(route('competitions.availability.reminders', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __('Reminder sent to :count participant.', ['count' => 1]),
        ]);

    Notification::assertCount(1);
});

test('a reminder is refused for an active competition without match days', function () {
    Notification::fake();

    $competition = Competition::factory()->create();
    $competition->participants()->attach(User::factory()->participant()->create());

    $this->post(route('competitions.availability.reminders', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => __('Add match days before sending a reminder.'),
        ]);

    Notification::assertNothingSent();
    expect($competition->refresh()->availability_reminder_sent_at)->toBeNull();
});

test('a round whose window was already claimed sends nothing', function () {
    Notification::fake();

    $competition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);
    $competition->participants()->attach(User::factory()->participant()->create());

    $sendAvailabilityReminders = app(SendAvailabilityReminders::class);

    expect($sendAvailabilityReminders->handle($competition))->toBe(1);

    Notification::fake();

    // Staat voor een tweede, gelijktijdig verzoek: het venster is inmiddels
    // door de eerste ronde geclaimd, dus deze aanroep mag niets meer versturen.
    expect($sendAvailabilityReminders->handle($competition->refresh()))->toBeNull();

    Notification::assertNothingSent();
});

test('a participant who submitted an empty form gets no reminder', function () {
    Notification::fake();

    $competition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);
    $competition->participants()->attach(
        User::factory()->participant()->create(),
        ['availability_submitted_at' => now()],
    );

    $this->post(route('competitions.availability.reminders', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => __('Everyone has already filled in their availability.'),
        ]);

    Notification::assertNothingSent();
});

test('the edit page reports that a reminder can be sent', function () {
    $competition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);
    $competition->participants()->attach(User::factory()->participant()->create());

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('availabilityReminder.pending_count', 1)
            ->where('availabilityReminder.can_send', true)
            ->where('availabilityReminder.blocked_reason', null)
            ->where('availabilityReminder.available_at', null)
            ->etc(),
        );
});

test('the edit page reports why a reminder is blocked', function (string $reason) {
    $competition = Competition::factory()->create();

    if ($reason !== 'no_match_days') {
        MatchDay::factory()->create(['competition_id' => $competition]);
    }

    if ($reason !== 'none_pending') {
        $competition->participants()->attach(User::factory()->participant()->create());
    }

    if ($reason === 'inactive') {
        $competition->update(['status' => 'draft']);
    }

    if ($reason === 'window') {
        $competition->forceFill(['availability_reminder_sent_at' => now()])->save();
    }

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('availabilityReminder.can_send', false)
            ->where('availabilityReminder.blocked_reason', $reason)
            ->where(
                'availabilityReminder.available_at',
                $reason === 'window' ? now()->addHours(24)->toIso8601String() : null,
            )
            ->etc(),
        );
})->with(['inactive', 'no_match_days', 'window', 'none_pending']);
