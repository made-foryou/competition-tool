<?php

use App\Actions\Competitions\BuildStandings;
use App\Enums\MatchStatus;
use App\Enums\PointsSystem;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\User;
use App\Support\CompetitionSettings;
use App\Support\Standings\StandingRow;
use App\Support\Standings\Standings;

/**
 * Een actieve competitie zonder speeldagen met het gegeven aantal deelnemers,
 * oplopend op id.
 *
 * Eigen naam: Pest deelt één globale functienaamruimte over alle
 * testbestanden.
 *
 * @return array{0: Competition, 1: list<User>}
 */
function standingsFixture(int $participantCount, PointsSystem $pointsSystem = PointsSystem::WinTwoLossZero): array
{
    $competition = Competition::factory()
        ->withSettings(CompetitionSettings::fromArray(['points_system' => $pointsSystem]))
        ->create();

    /** @var list<User> $participants */
    $participants = User::factory()->participant()->count($participantCount)->create()->sortBy('id')->values()->all();

    $competition->participants()->attach(array_map(fn (User $user): int => $user->id, $participants));

    return [$competition, $participants];
}

/**
 * Een gespeelde wedstrijd met een uitslag per speler: de factory sorteert de
 * spelers canoniek en wisselt de scores mee, dus `$playerScore` blijft bij
 * `$player`.
 */
function standingsRecord(Competition $competition, User $player, User $opponent, int $playerScore, int $opponentScore): CompetitionMatch
{
    return CompetitionMatch::factory()->played()->create([
        'competition_id' => $competition->id,
        'first_player_id' => $player->id,
        'second_player_id' => $opponent->id,
        'first_player_score' => $playerScore,
        'second_player_score' => $opponentScore,
    ]);
}

function standingsBuild(Competition $competition): Standings
{
    return app(BuildStandings::class)->handle($competition);
}

/**
 * De telling van een regel als lijst, in een vaste volgorde: gespeeld,
 * gewonnen, verloren, games voor, games tegen, punten.
 *
 * @return list<int>
 */
function standingsTally(?StandingRow $row): array
{
    expect($row)->toBeInstanceOf(StandingRow::class);

    return [$row->played, $row->won, $row->lost, $row->gamesFor, $row->gamesAgainst, $row->points];
}

/**
 * Een openstaande wedstrijd met (achtergebleven) scores bestaat niet via de
 * app, maar bewijst dat de status beslist en niet alleen de scores.
 */
test('a pending match does not count, even with scores', function () {
    [$competition, [$anna, $bert, $carla]] = standingsFixture(3);
    standingsRecord($competition, $anna, $bert, 3, 1);
    CompetitionMatch::factory()->create([
        'competition_id' => $competition->id,
        'first_player_id' => $anna->id,
        'second_player_id' => $carla->id,
        'first_player_score' => 1,
        'second_player_score' => 3,
    ]);

    $standings = standingsBuild($competition);

    expect($standings->playedMatchCount)->toBe(1);
    expect(standingsTally($standings->rowFor($carla->id)))->toBe([0, 0, 0, 0, 0, 0]);
});

test('a played match without a complete score does not count', function (?int $firstPlayerScore, ?int $secondPlayerScore) {
    [$competition, [$anna, $bert]] = standingsFixture(2);
    $match = CompetitionMatch::factory()->create([
        'competition_id' => $competition->id,
        'first_player_id' => $anna->id,
        'second_player_id' => $bert->id,
    ]);
    CompetitionMatch::query()->whereKey($match->id)->update([
        'status' => MatchStatus::Played->value,
        'first_player_score' => $firstPlayerScore,
        'second_player_score' => $secondPlayerScore,
    ]);

    $standings = standingsBuild($competition);

    expect($standings->hasResults())->toBeFalse();
    expect(standingsTally($standings->rowFor($anna->id)))->toBe([0, 0, 0, 0, 0, 0]);
    expect(standingsTally($standings->rowFor($bert->id)))->toBe([0, 0, 0, 0, 0, 0]);
})->with([
    'no scores' => [null, null],
    'first score missing' => [null, 3],
    'second score missing' => [3, null],
]);

/**
 * De rij wordt na het aanmaken omgedraaid (hoogste id als first player),
 * zoals de sync, de planner of een testhelper buiten de `saving`-hook om kan
 * schrijven. De dataset kiest wie wint, zodat beide kolommen een winnaar
 * leveren.
 */
test('a non-canonical row counts per column', function (string $winnerHas) {
    [$competition, [$low, $high]] = standingsFixture(2);
    [$winner, $loser] = $winnerHas === 'lowest id' ? [$low, $high] : [$high, $low];
    $match = CompetitionMatch::factory()->create([
        'competition_id' => $competition->id,
        'first_player_id' => $low->id,
        'second_player_id' => $high->id,
    ]);
    CompetitionMatch::query()->whereKey($match->id)->update([
        'first_player_id' => $high->id,
        'second_player_id' => $low->id,
        'status' => MatchStatus::Played->value,
        'first_player_score' => $high->is($winner) ? 3 : 1,
        'second_player_score' => $low->is($winner) ? 3 : 1,
    ]);

    $standings = standingsBuild($competition);

    expect(standingsTally($standings->rowFor($winner->id)))->toBe([1, 1, 0, 3, 1, 2]);
    expect(standingsTally($standings->rowFor($loser->id)))->toBe([1, 0, 1, 1, 3, 0]);
})->with([
    'lowest id' => 'lowest id',
    'highest id' => 'highest id',
]);

test('the points system of the competition is used', function () {
    [$competition, [$anna, $bert]] = standingsFixture(2, PointsSystem::GamesWon);
    standingsRecord($competition, $anna, $bert, 3, 2);

    $standings = standingsBuild($competition);

    expect($standings->pointsSystem)->toBe(PointsSystem::GamesWon)
        ->and($standings->rowFor($anna->id)?->points)->toBe(3)
        ->and($standings->rowFor($bert->id)?->points)->toBe(2);
});

test('matches and players of another competition are left out', function () {
    [$competition, [$anna, $bert]] = standingsFixture(2);
    [$other, [$outsider]] = standingsFixture(1);
    $other->participants()->attach($anna);
    standingsRecord($other, $anna, $outsider, 3, 0);

    $standings = standingsBuild($competition);

    expect($standings->playedMatchCount)->toBe(0)
        ->and($standings->rowFor($outsider->id))->toBeNull();
    expect(standingsTally($standings->rowFor($anna->id)))->toBe([0, 0, 0, 0, 0, 0]);
});

test('a player who left stays in the standings and the opponent keeps the points', function () {
    [$competition, [$stayer, $leaver]] = standingsFixture(2);
    standingsRecord($competition, $stayer, $leaver, 3, 1);
    $competition->participants()->detach($leaver);

    $standings = standingsBuild($competition);

    expect($standings->rowFor($leaver->id)?->player->isParticipant)->toBeFalse()
        ->and($standings->rowFor($stayer->id)?->player->isParticipant)->toBeTrue();
    expect(standingsTally($standings->rowFor($leaver->id)))->toBe([1, 0, 1, 1, 3, 0]);
    expect(standingsTally($standings->rowFor($stayer->id)))->toBe([1, 1, 0, 3, 1, 2]);
});

test('a participant without a result is listed with zeros', function () {
    [$competition, [$anna, $bert, $carla]] = standingsFixture(3);
    standingsRecord($competition, $anna, $bert, 3, 1);

    $standings = standingsBuild($competition);

    expect($standings->rowFor($carla->id)?->player->isParticipant)->toBeTrue();
    expect(standingsTally($standings->rowFor($carla->id)))->toBe([0, 0, 0, 0, 0, 0]);
});

test('a linked admin is a player and an unlinked admin is not listed', function () {
    [$competition, [$participant]] = standingsFixture(1);
    $linkedAdmin = User::factory()->create();
    $competition->participants()->attach($linkedAdmin);
    $unlinkedAdmin = User::factory()->create();

    $standings = standingsBuild($competition);

    expect(array_map(fn (StandingRow $row): int => $row->player->id, $standings->rows))
        ->toEqualCanonicalizing([$participant->id, $linkedAdmin->id])
        ->not->toContain($unlinkedAdmin->id);
});

test('without a played match there are no results but the participants are listed', function () {
    [$competition, [$anna, $bert]] = standingsFixture(2);

    $standings = standingsBuild($competition);

    expect($standings->hasResults())->toBeFalse()
        ->and($standings->rows)->toHaveCount(2);
    expect(standingsTally($standings->rowFor($anna->id)))->toBe([0, 0, 0, 0, 0, 0]);
    expect(standingsTally($standings->rowFor($bert->id)))->toBe([0, 0, 0, 0, 0, 0]);
});

test('a player is shown by nickname when one is set', function () {
    [$competition] = standingsFixture(0);
    $withNickname = User::factory()->participant()->create(['name' => 'Jan Jansen', 'nickname' => 'Ace']);
    $withoutNickname = User::factory()->participant()->create(['name' => 'Piet Pieters', 'nickname' => null]);
    $competition->participants()->attach([$withNickname->id, $withoutNickname->id]);

    $standings = standingsBuild($competition);

    expect($standings->rowFor($withNickname->id)?->player->displayName)->toBe('Ace')
        ->and($standings->rowFor($withoutNickname->id)?->player->displayName)->toBe('Piet Pieters');
});
