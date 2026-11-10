<?php

use App\Http\Middleware\EnsureAvailabilityIsSubmitted;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\User;

/**
 * Een Inertia-paginabezoek, zoals de client het doet bij een link of redirect.
 */
function inertiaVisit(string $url): array
{
    return [
        'url' => $url,
        'headers' => [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        ],
    ];
}

beforeEach(function () {
    $this->competition = Competition::factory()->create();
    $this->matchDay = MatchDay::factory()->create(['competition_id' => $this->competition]);

    $this->user = User::factory()->participant()->create();
    $this->competition->participants()->attach($this->user);
});

test('a participant without submitted availability is sent to the form', function () {
    $this->actingAs($this->user)
        ->get(route('competition.dashboard', $this->competition))
        ->assertRedirect(route('competition.availability.edit', $this->competition));
});

test('the settings pages are blocked as well', function () {
    $this->actingAs($this->user)
        ->get(route('competition.settings.index', $this->competition))
        ->assertRedirect(route('competition.availability.edit', $this->competition));
});

test('the form itself stays reachable', function () {
    $this->actingAs($this->user)
        ->get(route('competition.availability.edit', $this->competition))
        ->assertOk();
});

test('the dashboard is reachable once the availability is submitted', function () {
    $this->actingAs($this->user)
        ->put(route('competition.availability.update', $this->competition), []);

    $this->actingAs($this->user)
        ->get(route('competition.dashboard', $this->competition))
        ->assertOk();
});

test('a competition without match days does not block', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.dashboard', $competition))
        ->assertOk();
});

test('a finished competition does not block', function () {
    $competition = Competition::factory()->finished()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);

    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.dashboard', $competition))
        ->assertOk();
});

test('a draft competition stays hidden instead of redirecting to the form', function () {
    $competition = Competition::factory()->draft()->create();
    MatchDay::factory()->create(['competition_id' => $competition]);

    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.dashboard', $competition))
        ->assertNotFound();
});

test('an admin who participates without submitted availability is sent to the form', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $this->competition->participants()->attach($admin);

    $this->actingAs($admin)
        ->get(route('competitions.edit', $this->competition))
        ->assertRedirect(route('competition.availability.edit', $this->competition));
});

test('an admin who does not participate is not blocked', function () {
    $admin = User::factory()->withTwoFactor()->create();

    $this->actingAs($admin)
        ->get(route('competitions.edit', $this->competition))
        ->assertOk();
});

test('an admin participant without a second factor is sent to two factor setup first', function () {
    $admin = User::factory()->create();
    $this->competition->participants()->attach($admin);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('two-factor.setup'));

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.setup'))
        ->assertOk();
});

test('guests are not affected', function () {
    $this->get(route('competition.login', $this->competition))->assertOk();
});

test('after submitting one competition the next open one follows', function () {
    $second = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $second]);
    $second->participants()->attach($this->user);

    $this->actingAs($this->user)
        ->put(route('competition.availability.update', $this->competition), []);

    $this->actingAs($this->user)
        ->get(route('competition.dashboard', $this->competition))
        ->assertRedirect(route('competition.availability.edit', $second));
});

test('a blocked write request is redirected with 303 so the method is not replayed', function () {
    $this->actingAs($this->user)
        ->patch(route('competition.settings.profile.update', $this->competition), ['name' => 'Ander'])
        ->assertStatus(303)
        ->assertRedirect(route('competition.availability.edit', $this->competition));
});

test('a participant who already submitted is sent back to the form after a match day is added', function () {
    $this->actingAs($this->user)
        ->put(route('competition.availability.update', $this->competition), [
            'match_days' => [$this->matchDay->id],
        ]);
    $this->actingAs($this->user)
        ->get(route('competition.dashboard', $this->competition))
        ->assertOk();

    $newDate = $this->matchDay->date->addDay()->toDateString();
    $this->competition->update(['ends_at' => $newDate]);

    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->post(route('competitions.match-days.store', $this->competition), [
            'date' => $newDate,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'field_count' => 1,
        ])->assertSessionHasNoErrors();

    $newDay = MatchDay::query()->whereDate('date', $newDate)->firstOrFail();

    $this->actingAs($this->user)
        ->get(route('competition.dashboard', $this->competition))
        ->assertRedirect(route('competition.availability.edit', $this->competition));

    $this->actingAs($this->user)
        ->put(route('competition.availability.update', $this->competition), [
            'match_days' => [$this->matchDay->id, $newDay->id],
        ]);

    $this->actingAs($this->user)
        ->get(route('competition.dashboard', $this->competition))
        ->assertOk();

    expect($this->user->matchDayAvailabilities()->where('match_day_id', $newDay->id)->exists())->toBeTrue();
});

test('an admin who participates is sent to the form after adding a match day themselves', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $this->competition->participants()->attach($admin, ['availability_submitted_at' => now()]);

    $newDate = $this->matchDay->date->addDay()->toDateString();
    $this->competition->update(['ends_at' => $newDate]);

    $this->actingAs($admin)
        ->post(route('competitions.match-days.store', $this->competition), [
            'date' => $newDate,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'field_count' => 1,
        ])->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->get(route('competitions.edit', $this->competition))
        ->assertRedirect(route('competition.availability.edit', $this->competition));

    $this->actingAs($admin)
        ->put(route('competition.availability.update', $this->competition), [])
        ->assertRedirect(route('competitions.edit', $this->competition));
});

test('after the first submission a participant returns to the page they wanted', function () {
    $visit = inertiaVisit(route('competition.settings.index', $this->competition));

    $this->actingAs($this->user)
        ->get($visit['url'], $visit['headers'])
        ->assertRedirect(route('competition.availability.edit', $this->competition));

    $this->actingAs($this->user)
        ->put(route('competition.availability.update', $this->competition), [])
        ->assertRedirect(route('competition.settings.index', $this->competition));
});

test('an admin returns to the management tab they came from after submitting', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $this->competition->participants()->attach($admin);

    $managementUrl = route('competitions.edit', $this->competition).'?tab=match-days';

    $visit = inertiaVisit($managementUrl);

    $this->actingAs($admin)
        ->get($visit['url'], $visit['headers'])
        ->assertRedirect(route('competition.availability.edit', $this->competition));

    $this->actingAs($admin)
        ->put(route('competition.availability.update', $this->competition), [])
        ->assertRedirect($managementUrl);
});

test('a blocked write request is not remembered as the page to return to', function () {
    $this->actingAs($this->user)
        ->patch(route('competition.settings.profile.update', $this->competition), ['name' => 'Ander'])
        ->assertStatus(303);

    $this->actingAs($this->user)
        ->put(route('competition.availability.update', $this->competition), [])
        ->assertRedirect(route('competition.dashboard', $this->competition));
});

test('changing availability later does not use a remembered page', function () {
    $this->competition->participants()->updateExistingPivot($this->user->id, ['availability_submitted_at' => now()]);

    $this->actingAs($this->user)
        ->withSession([EnsureAvailabilityIsSubmitted::INTENDED_URL_SESSION_KEY => [
            'competition_id' => $this->competition->id,
            'url' => route('competition.settings.index', $this->competition),
        ]])
        ->put(route('competition.availability.update', $this->competition), [])
        ->assertRedirect(route('competition.availability.edit', $this->competition));
});

test('a json request is not remembered as the page to return to', function () {
    $this->actingAs($this->user)
        ->getJson(route('competition.settings.index', $this->competition))
        ->assertRedirect(route('competition.availability.edit', $this->competition));

    $this->actingAs($this->user)
        ->put(route('competition.availability.update', $this->competition), [])
        ->assertRedirect(route('competition.dashboard', $this->competition));
});

test('a plain browser request is not remembered as the page to return to', function () {
    $this->actingAs($this->user)
        ->get(route('competition.settings.index', $this->competition))
        ->assertRedirect(route('competition.availability.edit', $this->competition));

    $this->actingAs($this->user)
        ->put(route('competition.availability.update', $this->competition), [])
        ->assertRedirect(route('competition.dashboard', $this->competition));
});

test('a remembered page of another competition is not used', function () {
    $other = Competition::factory()->create();

    $this->actingAs($this->user)
        ->withSession([EnsureAvailabilityIsSubmitted::INTENDED_URL_SESSION_KEY => [
            'competition_id' => $other->id,
            'url' => route('competition.settings.index', $other),
        ]])
        ->put(route('competition.availability.update', $this->competition), [])
        ->assertRedirect(route('competition.dashboard', $this->competition));
});
