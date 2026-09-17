<?php

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\CompetitionMatch;
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
            ->where('registrationState', 'open')
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

test('guests cannot register on a finished competition', function () {
    $finished = Competition::factory()->finished()->create();
    MatchDay::factory()->create(['competition_id' => $finished]);

    // Een bestaande deelnemer, zodat een geslaagde registratie een tweede
    // speler zou opleveren en de sync dus wel degelijk een wedstrijd zou
    // schrijven -- anders bewijst de assertie hieronder niets.
    $finished->participants()->attach(User::factory()->participant()->create());

    $this->post(route('competition.register.store', $finished), registrationPayload())
        ->assertRedirect(route('competition.register.show', $finished))
        ->assertSessionHas('status', 'Aanmelden voor deze competitie is gesloten. Neem contact op met de organisator als je vragen hebt.');

    expect(User::query()->where('email', 'sanne@example.com')->exists())->toBeFalse()
        ->and($finished->participants()->count())->toBe(1)
        ->and(CompetitionMatch::query()->where('competition_id', $finished->id)->count())->toBe(0);
});

test('a logged in participant cannot register on a finished competition', function () {
    $finished = Competition::factory()->finished()->create();
    $matchDay = MatchDay::factory()->create(['competition_id' => $finished]);
    $participant = User::factory()->participant()->create();

    $this->actingAs($participant)
        ->post(route('competition.register.store', $finished), [
            'match_days' => [$matchDay->id],
        ])
        ->assertRedirect(route('competition.register.show', $finished))
        ->assertSessionHas('status', 'Aanmelden voor deze competitie is gesloten. Neem contact op met de organisator als je vragen hebt.');

    expect($finished->participants()->count())->toBe(0)
        ->and($participant->matchDayAvailabilities()->count())->toBe(0);
});

test('an admin cannot register on a draft competition', function () {
    $draft = Competition::factory()->draft()->create();
    $admin = User::factory()->withTwoFactor()->create();

    $this->actingAs($admin)
        ->post(route('competition.register.store', $draft), [
            'match_days' => [],
        ])
        ->assertRedirect(route('competition.register.show', $draft))
        ->assertSessionHas('status', 'Aanmelden voor deze competitie is nog niet geopend.');

    expect($draft->participants()->count())->toBe(0);
});

test('guests viewing the registration page of a finished competition see a closed state', function () {
    $finished = Competition::factory()->finished()->create();
    MatchDay::factory()->count(2)->create(['competition_id' => $finished]);

    $this->get(route('competition.register.show', $finished))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/competition-register')
            ->where('registrationState', 'closed')
            ->where('returnUrl', null)
            ->where('passwordRules', '')
            ->has('matchDays', 0),
        );
});

test('an admin viewing the registration page of a draft competition sees an upcoming state', function () {
    $draft = Competition::factory()->draft()->create();
    MatchDay::factory()->count(2)->create(['competition_id' => $draft]);
    $admin = User::factory()->withTwoFactor()->create();

    $this->actingAs($admin)
        ->get(route('competition.register.show', $draft))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('registrationState', 'upcoming')
            ->where('returnUrl', route('dashboard'))
            ->has('matchDays', 0),
        );
});

test('a participant of a finished competition is sent to the dashboard when viewing the registration page', function () {
    $finished = Competition::factory()->finished()->create();
    $participant = User::factory()->participant()->create();
    $finished->participants()->attach($participant);

    $this->actingAs($participant)
        ->get(route('competition.register.show', $finished))
        ->assertRedirect(route('competition.dashboard', $finished));
});

test('registration stays open on an active competition that has not started yet', function () {
    $upcoming = Competition::factory()->create([
        'starts_at' => now()->addMonth()->toDateString(),
        'ends_at' => now()->addMonth()->addDay()->toDateString(),
    ]);
    $matchDay = MatchDay::factory()->create([
        'competition_id' => $upcoming,
        'date' => now()->addMonth()->toDateString(),
    ]);

    $this->post(route('competition.register.store', $upcoming), registrationPayload([
        'email' => 'toekomst@example.com',
        'match_days' => [$matchDay->id],
    ]))->assertRedirect(route('competition.dashboard', $upcoming));

    expect($upcoming->participants()->count())->toBe(1);
});

test('a signed in non-participant sees the closed state of a finished competition', function () {
    $finished = Competition::factory()->finished()->create();
    $user = User::factory()->participant()->create();

    $this->actingAs($user)
        ->get(route('competition.register.show', $finished))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/competition-register')
            ->where('authenticated', true)
            ->where('registrationState', 'closed')
            ->where('returnUrl', route('competition.none')),
        );
});

test('the registration page shows why a submitted form was refused', function () {
    $finished = Competition::factory()->finished()->create();

    $this->post(route('competition.register.store', $finished), registrationPayload())
        ->assertRedirect(route('competition.register.show', $finished));

    // Dezelfde sessie, dus de flash van de redirect hierboven staat nog
    // klaar voor de pagina waar de bezoeker op landt.
    $this->get(route('competition.register.show', $finished))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/competition-register')
            ->where('registrationState', 'closed')
            ->where('status', 'Aanmelden voor deze competitie is gesloten. Neem contact op met de organisator als je vragen hebt.'),
        );
});
