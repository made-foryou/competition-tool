<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Een actieve competitie zonder speeldagen (dan stuurt
 * EnsureAvailabilityIsSubmitted niet om) met `$count` deelnemers die elkaar
 * één keer troffen: speler 1 wint van iedereen, speler 2 van iedereen behalve
 * speler 1, enzovoort. De stand is dus altijd speler 1 t/m `$count`.
 *
 * Eigen naam: Pest deelt één globale functienaamruimte over alle testbestanden.
 *
 * @return array{0: Competition, 1: list<User>}
 */
function participantStandingsFixture(int $count = 5): array
{
    $competition = Competition::factory()->create();

    /** @var list<User> $players */
    $players = User::factory()->participant()->count($count)->create()->all();
    $competition->participants()->attach(array_map(fn (User $user): int => $user->id, $players));

    foreach ($players as $index => $winner) {
        foreach (array_slice($players, $index + 1) as $loser) {
            participantStandingsPlay($competition, $winner, $loser);
        }
    }

    return [$competition, $players];
}

function participantStandingsPlay(Competition $competition, User $winner, User $loser): CompetitionMatch
{
    return CompetitionMatch::factory()->played()->create([
        'competition_id' => $competition->id,
        'first_player_id' => $winner->id,
        'second_player_id' => $loser->id,
        'first_player_score' => 3,
        'second_player_score' => 0,
    ]);
}

/**
 * Een Inertia-partial-reload van één prop, zoals de browser die na het laden
 * van een deferred prop doet.
 */
function participantStandingsPartial(string $component, string $prop): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => $component,
        'X-Inertia-Partial-Data' => $prop,
    ];
}

test('the dashboard summary shows the top three and the viewer row after a gap', function () {
    [$competition, $players] = participantStandingsFixture(6);

    $this->actingAs($players[4])
        ->get(route('competition.dashboard', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('standingsSummary')
            ->loadDeferredProps('standings', fn (Assert $reload) => $reload
                ->has('standingsSummary.leaders', 3)
                ->where('standingsSummary.leaders.0.player_id', $players[0]->id)
                ->where('standingsSummary.leaders.2.player_id', $players[2]->id)
                ->where('standingsSummary.leaders.0.is_viewer', false)
                ->where('standingsSummary.viewer_row.player_id', $players[4]->id)
                ->where('standingsSummary.viewer_row.position', 5)
                ->where('standingsSummary.viewer_row.is_viewer', true)
                ->where('standingsSummary.has_gap', true)
                ->where('standingsSummary.total', 6)));
});

test('the viewer in fourth place gets a row without a gap', function () {
    [$competition, $players] = participantStandingsFixture(5);

    $this->actingAs($players[3])
        ->get(route('competition.dashboard', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('standings', fn (Assert $reload) => $reload
                ->where('standingsSummary.viewer_row.player_id', $players[3]->id)
                ->where('standingsSummary.viewer_row.position', 4)
                ->where('standingsSummary.has_gap', false)));
});

test('the viewer in the top three has no separate row', function () {
    [$competition, $players] = participantStandingsFixture(5);

    $this->actingAs($players[1])
        ->get(route('competition.dashboard', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('standings', fn (Assert $reload) => $reload
                ->where('standingsSummary.viewer_row', null)
                ->where('standingsSummary.has_gap', false)
                ->where('standingsSummary.leaders.1.player_id', $players[1]->id)
                ->where('standingsSummary.leaders.1.is_viewer', true)
                ->where('standingsSummary.total', 5)));
});

test('the standings page lists every row with the own row marked', function () {
    [$competition, $players] = participantStandingsFixture(5);

    $this->actingAs($players[4])
        ->get(route('competition.standings', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('participant/standings')
            ->where('competition.slug', $competition->slug)
            ->where('competition.status', 'active')
            ->missing('standings')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('standings.rows', 5)
                ->where('standings.rows.0.player_id', $players[0]->id)
                ->where('standings.rows.0.is_viewer', false)
                ->where('standings.rows.4.player_id', $players[4]->id)
                ->where('standings.rows.4.position', 5)
                ->where('standings.rows.4.is_viewer', true)));
});

test('opponents are shown by nickname and their real name is never sent', function (string $component, string $routeName, string $prop, string $nicknamePath) {
    [$competition, $players] = participantStandingsFixture(4);
    $players[0]->update(['name' => 'Sanne de Vries', 'nickname' => 'Speedy']);

    $content = $this->actingAs($players[3])
        ->get(route($routeName, $competition), participantStandingsPartial($component, $prop))
        ->assertOk()
        ->assertJsonPath($nicknamePath, 'Speedy')
        ->getContent();

    expect($content)->toContain('Speedy')->not->toContain('Sanne de Vries');
})->with([
    'dashboard' => ['participant/dashboard', 'competition.dashboard', 'standingsSummary', 'props.standingsSummary.leaders.0.player'],
    'page' => ['participant/standings', 'competition.standings', 'standings', 'props.standings.rows.0.player'],
]);

test('an admin who is not linked sees the standings without an own row', function () {
    [$competition, $players] = participantStandingsFixture(5);
    $admin = User::factory()->withTwoFactor()->create();

    $this->actingAs($admin)
        ->get(route('competition.dashboard', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('standingsSummary')
            ->loadDeferredProps('standings', fn (Assert $reload) => $reload
                ->where('standingsSummary.viewer_row', null)
                ->where('standingsSummary.has_gap', false)
                ->where('standingsSummary.leaders.0.is_viewer', false)
                ->where('standingsSummary.leaders.1.is_viewer', false)
                ->where('standingsSummary.leaders.2.is_viewer', false)
                ->where('standingsSummary.total', 5)));

    $this->actingAs($admin)
        ->get(route('competition.standings', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('standings.rows', 5)
                ->where('standings.rows.0.is_viewer', false)
                ->where('standings.rows.4.is_viewer', false)));
});

test('an unlinked admin who played earlier sees their own row as a departed player', function () {
    [$competition, $players] = participantStandingsFixture(3);
    $admin = User::factory()->withTwoFactor()->create();
    $competition->participants()->attach($admin);
    participantStandingsPlay($competition, $players[0], $admin);
    $competition->participants()->detach($admin);

    $this->actingAs($admin)
        ->get(route('competition.standings', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('standings.rows', 4)
                ->where('standings.rows.2.player_id', $admin->id)
                ->where('standings.rows.2.is_viewer', true)
                ->where('standings.rows.2.player_is_participant', false)));
});

test('the standings page requires a login and a participation', function () {
    $competition = Competition::factory()->create();

    $this->get(route('competition.standings', $competition))
        ->assertRedirect(route('competition.login', $competition));

    $this->actingAs(User::factory()->participant()->create())
        ->get(route('competition.standings', $competition))
        ->assertRedirect(route('competition.register.show', $competition));
});

test('without results the summary and the page are empty', function () {
    $competition = Competition::factory()->create();
    $viewer = User::factory()->participant()->create();
    $competition->participants()->attach($viewer);

    $this->actingAs($viewer)
        ->get(route('competition.dashboard', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('standings', fn (Assert $reload) => $reload
                ->where('standingsSummary.total', 0)
                ->where('standingsSummary.leaders', [])
                ->where('standingsSummary.viewer_row', null)
                ->where('standingsSummary.has_gap', false)));

    $this->actingAs($viewer)
        ->get(route('competition.standings', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('standings.rows', [])));
});
