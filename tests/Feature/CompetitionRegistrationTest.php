<?php

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->competition = Competition::factory()->create([
        'starts_at' => '2026-10-01',
        'ends_at' => '2026-10-10',
    ]);

    $this->firstDay = MatchDay::factory()->create([
        'competition_id' => $this->competition,
        'date' => '2026-10-02',
    ]);
    $this->secondDay = MatchDay::factory()->create([
        'competition_id' => $this->competition,
        'date' => '2026-10-03',
    ]);
});

function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'Sanne de Vries',
        'nickname' => '',
        'email' => 'sanne@example.com',
        'password' => 'wachtwoord-van-sanne',
        'password_confirmation' => 'wachtwoord-van-sanne',
        'match_days' => [],
        ...$overrides,
    ];
}

test('guests can view the registration page of an active competition', function () {
    $this->get(route('competition.register.show', $this->competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/competition-register')
            ->where('authenticated', false)
            ->has('matchDays', 2),
        );
});

test('the registration page of a draft competition is hidden', function () {
    $draft = Competition::factory()->draft()->create();

    $this->get(route('competition.register.show', $draft))->assertNotFound();
});

test('signing up creates a participant, links the competition and logs in', function () {
    $this->post(route('competition.register.store', $this->competition), registrationPayload([
        'match_days' => [$this->firstDay->id],
    ]))->assertRedirect(route('competition.dashboard', $this->competition));

    $user = User::query()->where('email', 'sanne@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Participant)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->competitions()->count())->toBe(1)
        ->and($user->matchDayAvailabilities()->pluck('match_day_id')->all())->toBe([$this->firstDay->id]);

    $this->assertAuthenticatedAs($user);
});

test('signing up marks the availability as submitted', function () {
    $this->post(route('competition.register.store', $this->competition), registrationPayload());

    $user = User::query()->where('email', 'sanne@example.com')->firstOrFail();

    expect($user->hasSubmittedAvailabilityFor($this->competition))->toBeTrue()
        ->and($user->matchDayAvailabilities()->count())->toBe(0);
});

test('a nickname is stored and an empty one becomes null', function () {
    $this->post(route('competition.register.store', $this->competition), registrationPayload([
        'nickname' => 'MadMax',
    ]));

    expect(User::query()->where('email', 'sanne@example.com')->firstOrFail()->nickname)->toBe('MadMax');

    auth()->logout();

    $this->post(route('competition.register.store', $this->competition), registrationPayload([
        'email' => 'peter@example.com',
    ]));

    expect(User::query()->where('email', 'peter@example.com')->firstOrFail()->nickname)->toBeNull();
});

test('an existing email address is rejected', function () {
    User::factory()->participant()->create(['email' => 'sanne@example.com']);

    $this->post(route('competition.register.store', $this->competition), registrationPayload())
        ->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'sanne@example.com')->count())->toBe(1);
});

test('a match day of another competition is rejected', function () {
    $other = MatchDay::factory()->create();

    $this->post(route('competition.register.store', $this->competition), registrationPayload([
        'match_days' => [$other->id],
    ]))->assertSessionHasErrors('match_days.0');

    expect(MatchDayAvailability::query()->count())->toBe(0);
});

test('a participant of the competition is sent to the dashboard', function () {
    $user = User::factory()->participant()->create();
    $this->competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.register.show', $this->competition))
        ->assertRedirect(route('competition.dashboard', $this->competition));
});

test('a signed in user who is not a participant can join without a new account', function () {
    $user = User::factory()->participant()->create();

    $this->actingAs($user)
        ->get(route('competition.register.show', $this->competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('authenticated', true));

    $this->actingAs($user)
        ->post(route('competition.register.store', $this->competition), [
            'match_days' => [$this->secondDay->id],
        ])
        ->assertRedirect(route('competition.dashboard', $this->competition));

    expect(User::query()->count())->toBe(1)
        ->and($user->competitions()->count())->toBe(1)
        ->and($user->matchDayAvailabilities()->pluck('match_day_id')->all())->toBe([$this->secondDay->id]);
});

test('signing up is rate limited', function () {
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->post(route('competition.register.store', $this->competition), registrationPayload([
            'email' => "deelnemer{$attempt}@example.com",
        ]));
        auth()->logout();
    }

    $this->post(route('competition.register.store', $this->competition), registrationPayload([
        'email' => 'laatste@example.com',
    ]))->assertStatus(429);
});

test('the pivot records when the availability was submitted', function () {
    $this->post(route('competition.register.store', $this->competition), registrationPayload());

    expect(DB::table('competition_user')->whereNotNull('availability_submitted_at')->count())->toBe(1);
});
