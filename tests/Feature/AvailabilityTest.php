<?php

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->competition = Competition::factory()->create();
    $this->firstDay = MatchDay::factory()->create(['competition_id' => $this->competition, 'date' => '2026-10-02']);
    $this->secondDay = MatchDay::factory()->create(['competition_id' => $this->competition, 'date' => '2026-10-03']);

    $this->user = User::factory()->participant()->create();
    $this->competition->participants()->attach($this->user, ['availability_submitted_at' => now()]);

    $this->actingAs($this->user);
});

test('a participant sees the match days with their current availability', function () {
    MatchDayAvailability::factory()->create([
        'match_day_id' => $this->firstDay,
        'user_id' => $this->user,
    ]);

    $this->get(route('competition.availability.edit', $this->competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('participant/availability')
            ->has('matchDays', 2)
            ->where('matchDays.0.is_available', true)
            ->where('matchDays.1.is_available', false)
            ->where('hasSubmitted', true),
        );
});

test('a participant can save their availability', function () {
    $this->put(route('competition.availability.update', $this->competition), [
        'match_days' => [$this->firstDay->id, $this->secondDay->id],
    ])->assertRedirect();

    expect($this->user->matchDayAvailabilities()->count())->toBe(2);
});

test('saving again replaces the previous selection', function () {
    $this->put(route('competition.availability.update', $this->competition), [
        'match_days' => [$this->firstDay->id, $this->secondDay->id],
    ]);

    $this->put(route('competition.availability.update', $this->competition), [
        'match_days' => [$this->secondDay->id],
    ]);

    expect($this->user->matchDayAvailabilities()->pluck('match_day_id')->all())
        ->toBe([$this->secondDay->id]);
});

test('saving does not touch the availability of another competition', function () {
    $other = Competition::factory()->create();
    $otherDay = MatchDay::factory()->create(['competition_id' => $other]);
    $other->participants()->attach($this->user, ['availability_submitted_at' => now()]);

    MatchDayAvailability::factory()->create([
        'match_day_id' => $otherDay,
        'user_id' => $this->user,
    ]);

    $this->put(route('competition.availability.update', $this->competition), [
        'match_days' => [$this->firstDay->id],
    ]);

    expect($this->user->matchDayAvailabilities()->pluck('match_day_id')->sort()->values()->all())
        ->toBe(collect([$otherDay->id, $this->firstDay->id])->sort()->values()->all());
});

test('saving without any match day counts as submitted', function () {
    $user = User::factory()->participant()->create();
    $this->competition->participants()->attach($user);

    $this->actingAs($user)
        ->put(route('competition.availability.update', $this->competition), [])
        ->assertRedirect(route('competition.dashboard', $this->competition));

    expect($user->hasSubmittedAvailabilityFor($this->competition))->toBeTrue()
        ->and($user->matchDayAvailabilities()->count())->toBe(0);
});

test('a match day of another competition is rejected', function () {
    $otherDay = MatchDay::factory()->create();

    $this->put(route('competition.availability.update', $this->competition), [
        'match_days' => [$otherDay->id],
    ])->assertSessionHasErrors('match_days.0');
});

test('someone who does not participate is sent to the registration page', function () {
    $this->actingAs(User::factory()->participant()->create())
        ->get(route('competition.availability.edit', $this->competition))
        ->assertRedirect(route('competition.register.show', $this->competition));
});

test('guests are redirected to the competition login page', function () {
    auth()->logout();

    $this->get(route('competition.availability.edit', $this->competition))
        ->assertRedirect(route('competition.login', $this->competition));
});

test('a participant of a finished competition can no longer change their availability', function () {
    $competition = Competition::factory()->finished()->create();
    $matchDay = MatchDay::factory()->create(['competition_id' => $competition]);
    $user = User::factory()->participant()->create();
    $submittedAt = now()->subWeek();
    $competition->participants()->attach($user, ['availability_submitted_at' => $submittedAt]);

    $this->actingAs($user)
        ->put(route('competition.availability.update', $competition), [
            'match_days' => [$matchDay->id],
        ])
        ->assertForbidden();

    expect(MatchDayAvailability::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('competition_user')
            ->where('competition_id', $competition->id)
            ->where('user_id', $user->id)
            ->value('availability_submitted_at'))
        ->toEqual($submittedAt->toDateTimeString());
});

test('an admin who is a participant of a draft competition cannot save their availability', function () {
    $competition = Competition::factory()->draft()->create();
    $admin = User::factory()->withTwoFactor()->create();
    $competition->participants()->attach($admin);

    $this->actingAs($admin)
        ->put(route('competition.availability.update', $competition), [
            'match_days' => [],
        ])
        ->assertForbidden();

    expect(MatchDayAvailability::query()->where('user_id', $admin->id)->count())->toBe(0);
});

test('an admin who does not participate in an active competition cannot save their availability', function () {
    $competition = Competition::factory()->create();
    $matchDay = MatchDay::factory()->create(['competition_id' => $competition]);
    $admin = User::factory()->withTwoFactor()->create();

    // De middleware laat een niet-gekoppelde admin door (die mag meekijken),
    // terwijl een niet-gekoppelde deelnemer daar al een 303 naar de
    // inschrijfpagina krijgt -- zie CompetitionAccessTest. Dat de admin hier
    // wél op de pagina komt maar op de 403 uit `authorize()` stuit, is dus
    // geen tegenspraak: de middleware regelt toegang tot de pagina, de
    // FormRequest regelt of er geschreven mag worden.
    $this->actingAs($admin)
        ->put(route('competition.availability.update', $competition), [
            'match_days' => [$matchDay->id],
        ])
        ->assertForbidden();

    expect(MatchDayAvailability::query()->where('user_id', $admin->id)->count())->toBe(0);
});

test('a finished competition shows the availability page as closed', function () {
    $competition = Competition::factory()->finished()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user, ['availability_submitted_at' => now()]);

    $this->actingAs($user)
        ->get(route('competition.availability.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('availabilityState', 'closed'),
        );
});

test('a non-participating admin sees the availability page as not participating', function () {
    $competition = Competition::factory()->create();
    $admin = User::factory()->withTwoFactor()->create();

    $this->actingAs($admin)
        ->get(route('competition.availability.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('availabilityState', 'not-participating'),
        );
});

test('a draft competition shows the availability page as upcoming', function () {
    $competition = Competition::factory()->draft()->create();
    $admin = User::factory()->withTwoFactor()->create();
    $competition->participants()->attach($admin);

    $this->actingAs($admin)
        ->get(route('competition.availability.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('availabilityState', 'upcoming'),
        );
});

test('a participant of the active competition sees the availability page as open', function () {
    $this->get(route('competition.availability.edit', $this->competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('availabilityState', 'open'),
        );
});
