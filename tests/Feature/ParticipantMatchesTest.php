<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\MatchDay;
use App\Models\MatchDayField;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->freezeSecond());

/**
 * Een competitie met één speeldag (standaard in de toekomst) en één tafel,
 * waaraan de kijker en zijn tegenstanders zijn gekoppeld met een actuele
 * beschikbaarheidsinzending. De koppeling gebeurt ná het aanmaken van de
 * speeldag, anders stuurt de middleware de kijker naar het formulier.
 *
 * Eigen naam: Pest deelt één globale functienaamruimte over alle testbestanden.
 *
 * @param  list<User>  $others
 * @return array{competition: Competition, day: MatchDay, field: MatchDayField}
 */
function participantMatchesFixture(User $viewer, array $others = [], ?string $dayDate = null, ?Competition $competition = null): array
{
    $competition ??= Competition::factory()->create();
    $day = MatchDay::factory()->create(array_filter([
        'competition_id' => $competition->id,
        'date' => $dayDate,
    ]));
    $field = MatchDayField::factory()->create(['match_day_id' => $day->id]);

    foreach ([$viewer, ...$others] as $user) {
        $competition->participants()->attach($user, ['availability_submitted_at' => now()]);
    }

    return ['competition' => $competition, 'day' => $day, 'field' => $field];
}

function participantMatchesMake(Competition $competition, User $a, User $b): Factory
{
    return CompetitionMatch::factory()->state([
        'competition_id' => $competition->id,
        'first_player_id' => $a->id,
        'second_player_id' => $b->id,
    ]);
}

test('the opponent is the other player whichever side the viewer is on', function (string $viewerSide) {
    [$low, $high] = User::factory()->participant()->count(2)->create()->sortBy('id')->values()->all();
    [$viewer, $opponent] = $viewerSide === 'first' ? [$low, $high] : [$high, $low];
    ['competition' => $competition] = participantMatchesFixture($viewer, [$opponent]);
    participantMatchesMake($competition, $viewer, $opponent)->create();

    $this->actingAs($viewer)
        ->get(route('competition.matches', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('matchList')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matchList.matches', 1)
                ->where('matchList.matches.0.opponent', $opponent->display_name)
                ->where('matchList.matches.0.opponent_is_participant', true)));
})->with(['lowest id' => 'first', 'highest id' => 'second']);

test('scores and outcome are shown from the viewer perspective', function (string $viewerSide, int $viewerScore, int $opponentScore, string $outcome) {
    [$low, $high] = User::factory()->participant()->count(2)->create()->sortBy('id')->values()->all();
    [$viewer, $opponent] = $viewerSide === 'first' ? [$low, $high] : [$high, $low];
    ['competition' => $competition] = participantMatchesFixture($viewer, [$opponent]);

    // De factory sorteert de ids en wisselt de scores mee: de eerste score hoort bij de eerst meegegeven speler.
    participantMatchesMake($competition, $viewer, $opponent)->played()->create([
        'first_player_score' => $viewerScore,
        'second_player_score' => $opponentScore,
    ]);

    $this->actingAs($viewer)
        ->get(route('competition.matches', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('matchList.matches.0.state', 'played')
                ->where('matchList.matches.0.own_score', $viewerScore)
                ->where('matchList.matches.0.opponent_score', $opponentScore)
                ->where('matchList.matches.0.outcome', $outcome)));
})->with([
    'win as first player' => ['first', 3, 1, 'won'],
    'win as second player' => ['second', 3, 1, 'won'],
    'loss as first player' => ['first', 0, 3, 'lost'],
    'loss as second player' => ['second', 1, 3, 'lost'],
]);

test('only the own matches of this competition are listed', function () {
    $viewer = User::factory()->participant()->create();
    [$opponent, $third, $fourth] = User::factory()->participant()->count(3)->create()->all();

    ['competition' => $competition] = participantMatchesFixture($viewer, [$opponent, $third, $fourth]);
    ['competition' => $other] = participantMatchesFixture($viewer, [$opponent]);

    $own = participantMatchesMake($competition, $viewer, $opponent)->create();
    participantMatchesMake($competition, $third, $fourth)->create();
    participantMatchesMake($other, $viewer, $opponent)->create();

    $this->actingAs($viewer)
        ->get(route('competition.matches', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matchList.matches', 1)
                ->where('matchList.matches.0.id', $own->id)));
});

test('the state is derived per match', function () {
    $viewer = User::factory()->participant()->create();
    [$a, $b, $c, $d] = User::factory()->participant()->count(4)->create()->all();

    ['competition' => $competition, 'day' => $futureDay, 'field' => $futureField] = participantMatchesFixture($viewer, [$a, $b, $c, $d]);
    $pastDay = MatchDay::factory()->create(['competition_id' => $competition->id, 'date' => now()->subDay()->toDateString()]);
    $pastField = MatchDayField::factory()->create(['match_day_id' => $pastDay->id]);

    $upcoming = participantMatchesMake($competition, $viewer, $a)->scheduled($futureDay, $futureField, '19:00')->create();
    $awaiting = participantMatchesMake($competition, $viewer, $b)->scheduled($pastDay, $pastField, '19:00')->create();
    $unscheduled = participantMatchesMake($competition, $viewer, $c)->unschedulable()->create();
    $played = participantMatchesMake($competition, $viewer, $d)->played()->create();

    $this->actingAs($viewer)
        ->get(route('competition.matches', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('matchList.matches.0.id', $upcoming->id)
                ->where('matchList.matches.0.state', 'upcoming')
                ->where('matchList.matches.1.id', $awaiting->id)
                ->where('matchList.matches.1.state', 'awaiting_result')
                ->where('matchList.matches.2.id', $unscheduled->id)
                ->where('matchList.matches.2.state', 'unscheduled')
                ->where('matchList.matches.3.id', $played->id)
                ->where('matchList.matches.3.state', 'played')));
});

test('an open match on a finished competition is not played', function () {
    $viewer = User::factory()->participant()->create();
    $opponent = User::factory()->participant()->create();
    $competition = Competition::factory()->finished()->create();
    ['day' => $day, 'field' => $field] = participantMatchesFixture($viewer, [$opponent], competition: $competition);
    participantMatchesMake($competition, $viewer, $opponent)->scheduled($day, $field, '19:00')->create();

    $this->actingAs($viewer)
        ->get(route('competition.matches', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('matchList.matches.0.state', 'not_played')));
});

test('matches are ordered by time with unscheduled last and played newest first', function () {
    $viewer = User::factory()->participant()->create();
    [$a, $b, $c, $d, $e] = User::factory()->participant()->count(5)->create()->all();

    ['competition' => $competition, 'day' => $day, 'field' => $field] = participantMatchesFixture($viewer, [$a, $b, $c, $d, $e]);
    $earlierDay = MatchDay::factory()->create(['competition_id' => $competition->id, 'date' => now()->addDay()->toDateString()]);
    $earlierField = MatchDayField::factory()->create(['match_day_id' => $earlierDay->id]);

    $late = participantMatchesMake($competition, $viewer, $a)->scheduled($day, $field, '20:00')->create();
    $early = participantMatchesMake($competition, $viewer, $b)->scheduled($day, $field, '18:00')->create();
    $earliest = participantMatchesMake($competition, $viewer, $c)->scheduled($earlierDay, $earlierField, '21:00')->create();
    $unscheduled = participantMatchesMake($competition, $viewer, $d)->unschedulable()->create();
    $played = participantMatchesMake($competition, $viewer, $e)->played()->scheduled($earlierDay, $earlierField, '10:00')->create();
    $playedLater = participantMatchesMake($competition, $viewer, User::factory()->participant()->create())->played()->scheduled($day, $field, '10:00')->create();

    $this->actingAs($viewer)
        ->get(route('competition.matches', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('matchList.matches.0.id', $earliest->id)
                ->where('matchList.matches.1.id', $early->id)
                ->where('matchList.matches.2.id', $late->id)
                ->where('matchList.matches.3.id', $unscheduled->id)
                ->where('matchList.matches.4.id', $playedLater->id)
                ->where('matchList.matches.5.id', $played->id)));
});

test('the dashboard overview shows at most three next matches and correct counters', function () {
    $viewer = User::factory()->participant()->create();
    $opponents = User::factory()->participant()->count(7)->create()->all();

    ['competition' => $competition, 'day' => $day, 'field' => $field] = participantMatchesFixture($viewer, $opponents);

    $nextIds = [];
    foreach (['17:00', '18:00', '19:00'] as $index => $time) {
        $nextIds[] = participantMatchesMake($competition, $viewer, $opponents[$index])->scheduled($day, $field, $time)->create()->id;
    }
    participantMatchesMake($competition, $viewer, $opponents[3])->unschedulable()->create();
    participantMatchesMake($competition, $viewer, $opponents[4])->played()->create(['first_player_score' => 3, 'second_player_score' => 0]);
    participantMatchesMake($competition, $viewer, $opponents[5])->played()->create(['first_player_score' => 0, 'second_player_score' => 3]);
    participantMatchesMake($competition, $viewer, $opponents[6])->played()->create(['first_player_score' => 3, 'second_player_score' => 2]);

    $this->actingAs($viewer)
        ->get(route('competition.dashboard', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('isParticipant', true)
            ->missing('matchOverview')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matchOverview.next_matches', 3)
                ->where('matchOverview.next_matches.0.id', $nextIds[0])
                ->where('matchOverview.next_matches.1.id', $nextIds[1])
                ->where('matchOverview.next_matches.2.id', $nextIds[2])
                ->where('matchOverview.next_matches.0.state', 'upcoming')
                ->where('matchOverview.next_matches.1.state', 'upcoming')
                ->where('matchOverview.next_matches.2.state', 'upcoming')
                ->where('matchOverview.counts.total', 7)
                ->where('matchOverview.counts.open', 4)
                ->where('matchOverview.counts.unscheduled', 1)
                ->where('matchOverview.counts.not_played', 0)
                ->where('matchOverview.counts.played', 3)
                ->where('matchOverview.counts.won', 2)
                ->where('matchOverview.counts.lost', 1)));
});

test('the dashboard overview lists a single open match next to played ones', function () {
    $viewer = User::factory()->participant()->create();
    [$a, $b, $c] = User::factory()->participant()->count(3)->create()->all();
    ['competition' => $competition, 'day' => $day, 'field' => $field] = participantMatchesFixture($viewer, [$a, $b, $c]);

    $open = participantMatchesMake($competition, $viewer, $a)->scheduled($day, $field, '19:00')->create();
    participantMatchesMake($competition, $viewer, $b)->played()->create();
    participantMatchesMake($competition, $viewer, $c)->played()->create();

    $this->actingAs($viewer)
        ->get(route('competition.dashboard', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matchOverview.next_matches', 1)
                ->where('matchOverview.next_matches.0.id', $open->id)
                ->where('matchOverview.counts.open', 1)
                ->where('matchOverview.counts.played', 2)));
});

test('an awaiting result match comes after the upcoming match in the overview', function () {
    $viewer = User::factory()->participant()->create();
    [$a, $b] = User::factory()->participant()->count(2)->create()->all();
    ['competition' => $competition, 'day' => $futureDay, 'field' => $futureField] = participantMatchesFixture($viewer, [$a, $b]);
    $pastDay = MatchDay::factory()->create(['competition_id' => $competition->id, 'date' => now()->subDay()->toDateString()]);
    $pastField = MatchDayField::factory()->create(['match_day_id' => $pastDay->id]);

    $awaiting = participantMatchesMake($competition, $viewer, $a)->scheduled($pastDay, $pastField, '19:00')->create();
    $upcoming = participantMatchesMake($competition, $viewer, $b)->scheduled($futureDay, $futureField, '19:00')->create();

    $this->actingAs($viewer)
        ->get(route('competition.dashboard', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('matchOverview.next_matches.0.id', $upcoming->id)
                ->where('matchOverview.next_matches.1.id', $awaiting->id)
                ->where('matchOverview.next_matches.1.state', 'awaiting_result')));
});

test('only the first upcoming match is marked as next', function () {
    $viewer = User::factory()->participant()->create();
    [$a, $b] = User::factory()->participant()->count(2)->create()->all();
    ['competition' => $competition, 'day' => $day, 'field' => $field] = participantMatchesFixture($viewer, [$a, $b]);

    participantMatchesMake($competition, $viewer, $a)->scheduled($day, $field, '18:00')->create();
    participantMatchesMake($competition, $viewer, $b)->scheduled($day, $field, '19:00')->create();

    $this->actingAs($viewer)
        ->get(route('competition.matches', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('matchList.matches.0.is_next', true)
                ->where('matchList.matches.1.is_next', false)));
});

test('no match is next when there is only awaiting result and unscheduled', function () {
    $viewer = User::factory()->participant()->create();
    [$a, $b] = User::factory()->participant()->count(2)->create()->all();
    ['competition' => $competition] = participantMatchesFixture($viewer, [$a, $b]);
    $pastDay = MatchDay::factory()->create(['competition_id' => $competition->id, 'date' => now()->subDay()->toDateString()]);
    $pastField = MatchDayField::factory()->create(['match_day_id' => $pastDay->id]);

    participantMatchesMake($competition, $viewer, $a)->scheduled($pastDay, $pastField, '19:00')->create();
    participantMatchesMake($competition, $viewer, $b)->unschedulable()->create();

    $this->actingAs($viewer)
        ->get(route('competition.matches', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('matchList.matches.0.state', 'awaiting_result')
                ->where('matchList.matches.0.is_next', false)
                ->where('matchList.matches.1.state', 'unscheduled')
                ->where('matchList.matches.1.is_next', false)));
});

test('a match day dated today is upcoming', function () {
    $viewer = User::factory()->participant()->create();
    $opponent = User::factory()->participant()->create();
    ['competition' => $competition, 'day' => $day, 'field' => $field] = participantMatchesFixture($viewer, [$opponent], now()->toDateString());
    participantMatchesMake($competition, $viewer, $opponent)->scheduled($day, $field, '19:00')->create();

    $this->actingAs($viewer)
        ->get(route('competition.matches', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('matchList.matches.0.state', 'upcoming')));
});

test('an administrator who participates gets their own list', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $opponent = User::factory()->participant()->create();
    ['competition' => $competition] = participantMatchesFixture($admin, [$opponent]);
    participantMatchesMake($competition, $admin, $opponent)->create();

    $this->actingAs($admin)
        ->get(route('competition.matches', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isParticipant', true)
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matchList.matches', 1)
                ->where('matchList.counts.total', 1)));
});

test('a played match on a finished competition stays played next to a not played one', function () {
    $viewer = User::factory()->participant()->create();
    [$a, $b] = User::factory()->participant()->count(2)->create()->all();
    $competition = Competition::factory()->finished()->create();
    ['day' => $day, 'field' => $field] = participantMatchesFixture($viewer, [$a, $b], competition: $competition);
    participantMatchesMake($competition, $viewer, $a)->played()->create(['first_player_score' => 3, 'second_player_score' => 1]);
    participantMatchesMake($competition, $viewer, $b)->scheduled($day, $field, '19:00')->create();

    $this->actingAs($viewer)
        ->get(route('competition.matches', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('matchList.matches.0.state', 'not_played')
                ->where('matchList.matches.1.state', 'played')
                ->where('matchList.matches.1.own_score', 3)
                ->where('matchList.matches.1.opponent_score', 1)
                ->where('matchList.counts.not_played', 1)
                ->where('matchList.counts.played', 1)
                ->where('matchList.counts.open', 0)));
});

test('an opponent who is no longer a participant is flagged', function () {
    $viewer = User::factory()->participant()->create();
    $gone = User::factory()->participant()->create();
    ['competition' => $competition] = participantMatchesFixture($viewer);
    participantMatchesMake($competition, $viewer, $gone)->create();

    $this->actingAs($viewer)
        ->get(route('competition.matches', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('matchList.matches.0.opponent_is_participant', false)));
});

test('an admin who is not linked sees both pages without matches', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $competition = Competition::factory()->create();

    $this->actingAs($admin)
        ->get(route('competition.dashboard', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isParticipant', false)
            ->where('matchOverview', null));

    $this->actingAs($admin)
        ->get(route('competition.matches', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('participant/matches')
            ->where('isParticipant', false)
            ->where('matchList', null));
});

test('opponents are shown by nickname and the real name is never sent', function () {
    $viewer = User::factory()->participant()->create();
    $opponent = User::factory()->participant()->create(['name' => 'Sanne de Vries', 'nickname' => 'Speedy']);
    ['competition' => $competition] = participantMatchesFixture($viewer, [$opponent]);
    participantMatchesMake($competition, $viewer, $opponent)->create();

    $this->actingAs($viewer)
        ->get(route('competition.matches', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('matchList.matches.0.opponent', 'Speedy')));

    $content = $this->actingAs($viewer)
        ->get(route('competition.matches', $competition), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'participant/matches',
            'X-Inertia-Partial-Data' => 'matchList',
        ])
        ->assertOk()
        ->getContent();

    expect($content)->toContain('Speedy')->not->toContain('Sanne de Vries');
});

test('the matches page requires a login and a participation', function () {
    $competition = Competition::factory()->create();

    $this->get(route('competition.matches', $competition))
        ->assertRedirect(route('competition.login', $competition));

    $this->actingAs(User::factory()->participant()->create())
        ->get(route('competition.matches', $competition))
        ->assertRedirect(route('competition.register.show', $competition));
});
