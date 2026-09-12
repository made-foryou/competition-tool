<?php

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function actingAsDashboardAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    test()->actingAs($admin);

    return $admin;
}

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->withTwoFactor()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('participants cannot access the dashboard', function () {
    $user = User::factory()->participant()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('the dashboard shows the competition and match day counts', function () {
    actingAsDashboardAdmin();

    $active = Competition::factory()->create();
    Competition::factory()->draft()->create();
    Competition::factory()->finished()->create();

    MatchDay::factory()->for($active)->create(['date' => today()->addDays(3)->toDateString()]);
    MatchDay::factory()->for($active)->create(['date' => today()->toDateString()]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('kpis.competitions', 3)
            ->where('kpis.active_competitions', 1)
            ->where('kpis.upcoming_match_days', 2)
            ->etc(),
        );
});

test('a match day in the past is not counted as upcoming', function () {
    actingAsDashboardAdmin();

    $competition = Competition::factory()->create();
    MatchDay::factory()->for($competition)->create(['date' => today()->subDay()->toDateString()]);
    MatchDay::factory()->for($competition)->create(['date' => today()->addDay()->toDateString()]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.upcoming_match_days', 1)
            ->etc(),
        );
});

test('a participant in two competitions is counted once', function () {
    actingAsDashboardAdmin();

    $participant = User::factory()->participant()->create();
    $other = User::factory()->participant()->create();

    $first = Competition::factory()->create();
    $second = Competition::factory()->create();

    $first->participants()->attach([$participant->id, $other->id]);
    $second->participants()->attach($participant->id);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.participants', 2)
            ->etc(),
        );
});

test('the lists are deferred and loaded in a follow-up request', function () {
    actingAsDashboardAdmin();

    $competition = Competition::factory()->create(['name' => 'Najaarscompetitie']);
    $competition->participants()->attach(User::factory()->participant()->create());

    MatchDay::factory()->for($competition)->withFields(3)->create([
        'date' => today()->addDays(2)->toDateString(),
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('kpis')
            ->missing('upcomingMatchDays')
            ->missing('recentCompetitions')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('upcomingMatchDays', 1, fn (Assert $matchDay) => $matchDay
                    ->where('date', today()->addDays(2)->toDateString())
                    ->where('fields_count', 3)
                    ->where('competition.name', 'Najaarscompetitie')
                    ->etc(),
                )
                ->has('recentCompetitions', 1, fn (Assert $recent) => $recent
                    ->where('name', 'Najaarscompetitie')
                    ->where('participants_count', 1)
                    ->etc(),
                )
                ->etc(),
            )
            ->etc(),
        );
});

test('the upcoming match day list skips past match days and stops at five', function () {
    actingAsDashboardAdmin();

    $competition = Competition::factory()->create([
        'starts_at' => today()->subMonth()->toDateString(),
        'ends_at' => today()->addMonths(2)->toDateString(),
    ]);

    MatchDay::factory()->for($competition)->create(['date' => today()->subWeek()->toDateString()]);

    foreach (range(1, 6) as $day) {
        MatchDay::factory()->for($competition)->create([
            'date' => today()->addDays($day)->toDateString(),
        ]);
    }

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('upcomingMatchDays', 5)
                ->where('upcomingMatchDays.0.date', today()->addDay()->toDateString())
                ->etc(),
            )
            ->etc(),
        );
});
