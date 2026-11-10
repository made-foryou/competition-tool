<?php

use App\Actions\Competitions\ClearMatchResult;
use App\Actions\Competitions\RecordMatchResult;
use App\Actions\Competitions\SyncCompetitionMatches;
use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\User;
use App\Support\CompetitionSettings;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());
});

/**
 * Een actieve competitie met deelnemers en de bijbehorende round-robin, in
 * het gevraagde wedstrijdformat.
 *
 * Eigen naam: Pest deelt één globale functienaamruimte over alle
 * testbestanden, dus de fixtures van de andere wedstrijdtests zijn bezet.
 */
function resultFixture(int $players = 3, int $bestOf = CompetitionSettings::DEFAULT_BEST_OF): Competition
{
    $competition = Competition::factory()
        ->withSettings(CompetitionSettings::fromArray(['best_of' => $bestOf]))
        ->create();

    $competition->participants()->attach(User::factory()->participant()->count($players)->create()->modelKeys());

    app(SyncCompetitionMatches::class)->handle($competition);

    return $competition->refresh();
}

/**
 * Geeft een wedstrijd een uitslag zonder de route: via de query builder,
 * omdat de status bewust niet fillable is.
 */
function markMatchPlayed(CompetitionMatch $match, int $firstPlayerScore, int $secondPlayerScore): void
{
    CompetitionMatch::query()->whereKey($match->id)->update([
        'status' => MatchStatus::Played->value,
        'first_player_score' => $firstPlayerScore,
        'second_player_score' => $secondPlayerScore,
    ]);
}

test('an admin can record a result for the players as stored', function () {
    $competition = resultFixture();
    $match = $competition->matches()->first();

    $this->put(route('competitions.matches.result.update', [$competition, $match]), [
        'first_player_score' => 3,
        'second_player_score' => 1,
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Result saved: :first – :second :first_score–:second_score.', [
            'first' => $match->firstPlayer->display_name,
            'second' => $match->secondPlayer->display_name,
            'first_score' => 3,
            'second_score' => 1,
        ])]);

    $recorded = $match->fresh();

    expect($recorded->status)->toBe(MatchStatus::Played)
        ->and($recorded->first_player_id)->toBe($match->first_player_id)
        ->and($recorded->second_player_id)->toBe($match->second_player_id)
        ->and($recorded->first_player_score)->toBe(3)
        ->and($recorded->second_player_score)->toBe(1);
});

test('a recorded result can be corrected', function () {
    $competition = resultFixture();
    $match = $competition->matches()->first();
    markMatchPlayed($match, 3, 1);

    $this->put(route('competitions.matches.result.update', [$competition, $match]), [
        'first_player_score' => 2,
        'second_player_score' => 3,
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $corrected = $match->fresh();

    expect($corrected->status)->toBe(MatchStatus::Played)
        ->and($corrected->first_player_score)->toBe(2)
        ->and($corrected->second_player_score)->toBe(3);
});

test('the scores stay with their players when the row was not canonical yet', function () {
    $competition = resultFixture();
    $match = $competition->matches()->first();
    $lowerId = $match->first_player_id;
    $higherId = $match->second_player_id;

    // Een rij die de canonicalisatie-hook nooit zag: via de query builder
    // staat de speler met het hogere id nu als first player.
    CompetitionMatch::query()->whereKey($match->id)->update([
        'first_player_id' => $higherId,
        'second_player_id' => $lowerId,
    ]);

    // De 3 is voor de speler die op dit moment first is, dus die met het
    // hogere id.
    $this->put(route('competitions.matches.result.update', [$competition, $match]), [
        'first_player_score' => 3,
        'second_player_score' => 1,
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        // De toast volgt de opgeslagen, canonieke volgorde van de lijst.
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Result saved: :first – :second :first_score–:second_score.', [
            'first' => User::find($lowerId)->display_name,
            'second' => User::find($higherId)->display_name,
            'first_score' => 1,
            'second_score' => 3,
        ])]);

    $saved = $match->fresh();

    // Het opslaan maakt de rij canoniek, en de 3 verhuist mee met zijn speler.
    expect($saved->first_player_id)->toBe($lowerId)
        ->and($saved->second_player_id)->toBe($higherId)
        ->and($saved->first_player_score)->toBe(1)
        ->and($saved->second_player_score)->toBe(3);
});

test('the deferred match list carries the scores', function () {
    $competition = resultFixture();
    [$played, $pending] = $competition->matches()->get()->all();
    markMatchPlayed($played, 1, 3);

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('matches')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('matches.0.id', $played->id)
                ->where('matches.0.status', MatchStatus::Played->value)
                ->where('matches.0.first_player_score', 1)
                ->where('matches.0.second_player_score', 3)
                ->where('matches.1.id', $pending->id)
                ->where('matches.1.first_player_score', null)
                ->where('matches.1.second_player_score', null),
            ),
        );
});

test('an invalid best-of-5 result is rejected', function (array $payload, string $errorKey, string $message, array $replace) {
    $competition = resultFixture();
    $match = $competition->matches()->first();

    $this->put(route('competitions.matches.result.update', [$competition, $match]), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors([$errorKey => __($message, $replace)]);

    $unchanged = $match->fresh();

    expect($unchanged->status)->toBe(MatchStatus::Pending)
        ->and($unchanged->first_player_score)->toBeNull()
        ->and($unchanged->second_player_score)->toBeNull();
})->with([
    'negative' => [['first_player_score' => -1, 'second_player_score' => 3], 'first_player_score', 'Enter a score between 0 and :max.', ['max' => 3]],
    'non-numeric' => [['first_player_score' => 'drie', 'second_player_score' => 1], 'first_player_score', 'Enter a whole number.', []],
    'empty' => [['first_player_score' => '', 'second_player_score' => 3], 'first_player_score', 'Enter a score.', []],
    'missing' => [['first_player_score' => 3], 'second_player_score', 'Enter a score.', []],
    'decimal' => [['first_player_score' => '2.5', 'second_player_score' => 3], 'first_player_score', 'Enter a whole number.', []],
    'above the maximum' => [['first_player_score' => 4, 'second_player_score' => 1], 'first_player_score', 'Enter a score between 0 and :max.', ['max' => 3]],
    'a draw' => [['first_player_score' => 3, 'second_player_score' => 3], 'score', 'In best of :best_of, exactly one player must have :games games: the winner.', ['best_of' => 5, 'games' => 3]],
    'no winner' => [['first_player_score' => 2, 'second_player_score' => 1], 'score', 'In best of :best_of, exactly one player must have :games games: the winner.', ['best_of' => 5, 'games' => 3]],
    'an array' => [['first_player_score' => [3], 'second_player_score' => 1], 'first_player_score', 'Enter a whole number.', []],
    'second player non-numeric' => [['first_player_score' => 3, 'second_player_score' => 'een'], 'second_player_score', 'Enter a whole number.', []],
    'second player above the maximum' => [['first_player_score' => 3, 'second_player_score' => 4], 'second_player_score', 'Enter a score between 0 and :max.', ['max' => 3]],
]);

test('the pair error of a best-of-1 match speaks of a single game', function () {
    $competition = resultFixture(bestOf: 1);
    $match = $competition->matches()->first();

    $this->put(route('competitions.matches.result.update', [$competition, $match]), [
        'first_player_score' => 1,
        'second_player_score' => 1,
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['score' => __('In best of :best_of, exactly one player must have :games game: the winner.', [
            'best_of' => 1,
            'games' => 1,
        ])]);

    expect($match->fresh()->status)->toBe(MatchStatus::Pending);
});

test('a best-of-3 match accepts a winner with two games', function (int $firstPlayerScore, int $secondPlayerScore) {
    $competition = resultFixture(bestOf: 3);
    $match = $competition->matches()->first();

    $this->put(route('competitions.matches.result.update', [$competition, $match]), [
        'first_player_score' => $firstPlayerScore,
        'second_player_score' => $secondPlayerScore,
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($match->fresh()->status)->toBe(MatchStatus::Played);
})->with([
    '2-1' => [2, 1],
    '0-2' => [0, 2],
]);

test('a best-of-3 match rejects three games', function () {
    $competition = resultFixture(bestOf: 3);
    $match = $competition->matches()->first();

    $this->put(route('competitions.matches.result.update', [$competition, $match]), [
        'first_player_score' => 3,
        'second_player_score' => 1,
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['first_player_score' => __('Enter a score between 0 and :max.', ['max' => 2])]);

    expect($match->fresh()->status)->toBe(MatchStatus::Pending);
});

test('an admin can clear a result', function () {
    $competition = resultFixture();
    $match = $competition->matches()->first();
    markMatchPlayed($match, 3, 1);

    $this->delete(route('competitions.matches.result.destroy', [$competition, $match]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Result cleared.')]);

    $cleared = $match->fresh();

    expect($cleared->status)->toBe(MatchStatus::Pending)
        ->and($cleared->first_player_score)->toBeNull()
        ->and($cleared->second_player_score)->toBeNull();
});

test('a recorded result survives the departure of one of its players', function () {
    $competition = resultFixture();
    $match = $competition->matches()->first();

    $this->put(route('competitions.matches.result.update', [$competition, $match]), [
        'first_player_score' => 3,
        'second_player_score' => 2,
    ])->assertRedirect();

    $this->delete(route('competitions.participants.destroy', [$competition, $match->first_player_id]))
        ->assertRedirect();

    $this->assertModelExists($match);

    $kept = $match->fresh();

    expect($kept->status)->toBe(MatchStatus::Played)
        ->and($kept->first_player_score)->toBe(3)
        ->and($kept->second_player_score)->toBe(2);
});

test('clearing the result of a departed player removes the match', function () {
    $competition = resultFixture();
    $match = $competition->matches()->first();
    markMatchPlayed($match, 3, 1);

    $this->delete(route('competitions.participants.destroy', [$competition, $match->second_player_id]))
        ->assertRedirect();

    $this->assertModelExists($match);

    $this->delete(route('competitions.matches.result.destroy', [$competition, $match]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __('Result cleared. The match was removed because one of its players no longer participates.'),
        ]);

    $this->assertModelMissing($match);
});

test('clearing a match without a result changes nothing and confirms', function () {
    $competition = resultFixture();
    $match = $competition->matches()->first();

    $this->delete(route('competitions.matches.result.destroy', [$competition, $match]))
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Result cleared.')]);

    $unchanged = $match->fresh();

    expect($unchanged->status)->toBe(MatchStatus::Pending)
        ->and($unchanged->first_player_score)->toBeNull()
        ->and($unchanged->second_player_score)->toBeNull();
});

test('a result is not recorded when the competition stopped being active after the request started', function () {
    $competition = resultFixture();
    $match = $competition->matches()->first();

    // De Form Request keek vóór de rijlock naar de status; deze test staat
    // voor de beheerder die de competitie daarna afrondde. De Action kijkt
    // onder de lock opnieuw en weigert alsnog.
    Competition::query()->whereKey($competition->id)->update(['status' => CompetitionStatus::Finished->value]);

    expect(fn () => app(RecordMatchResult::class)->handle($match, 3, 1))->toThrow(
        ValidationException::class,
        __('Results can only be changed while the competition is active.'),
    );

    $unchanged = $match->fresh();

    expect($unchanged->status)->toBe(MatchStatus::Pending)
        ->and($unchanged->first_player_score)->toBeNull()
        ->and($unchanged->second_player_score)->toBeNull();
});

test('a result is not recorded when the match format changed after the request started', function () {
    $competition = resultFixture();
    $match = $competition->matches()->first();

    // De Form Request keurde 3–1 goed tegen best of 5; deze test staat voor
    // het format dat daarna naar best of 3 ging. Onder de lock past 3–1 niet
    // meer, en zou best of 3 met deze uitslag vergrendeld raken.
    Competition::query()->whereKey($competition->id)->update([
        'settings' => json_encode(CompetitionSettings::fromArray(['best_of' => 3])->toArray()),
    ]);

    expect(fn () => app(RecordMatchResult::class)->handle($match, 3, 1))->toThrow(
        ValidationException::class,
        __('In best of :best_of, exactly one player must have :games games: the winner.', ['best_of' => 3, 'games' => 2]),
    );

    $unchanged = $match->fresh();

    expect($unchanged->status)->toBe(MatchStatus::Pending)
        ->and($unchanged->first_player_score)->toBeNull()
        ->and($unchanged->second_player_score)->toBeNull();
});

test('a result is not cleared when the competition stopped being active after the request started', function () {
    $competition = resultFixture();
    $match = $competition->matches()->first();
    markMatchPlayed($match, 3, 1);

    Competition::query()->whereKey($competition->id)->update(['status' => CompetitionStatus::Finished->value]);

    expect(fn () => app(ClearMatchResult::class)->handle($match))->toThrow(
        ValidationException::class,
        __('Results can only be changed while the competition is active.'),
    );

    $unchanged = $match->fresh();

    expect($unchanged->status)->toBe(MatchStatus::Played)
        ->and($unchanged->first_player_score)->toBe(3)
        ->and($unchanged->second_player_score)->toBe(1);
});

test('clearing a match removed after the request started gives a 404', function () {
    $competition = resultFixture();
    $match = $competition->matches()->first();
    markMatchPlayed($match, 3, 1);

    // De route-binding las de wedstrijd vóór de rijlock; intussen is hij
    // verwijderd. De Action leest hem onder de lock opnieuw en geeft het op.
    CompetitionMatch::query()->whereKey($match->id)->delete();

    expect(fn () => app(ClearMatchResult::class)->handle($match))
        ->toThrow(ModelNotFoundException::class);

    $this->assertModelMissing($match);
});

test('a match removed after the request started gets no result', function () {
    $competition = resultFixture();
    $match = $competition->matches()->first();

    // De route-binding las de wedstrijd vóór de rijlock; deze test staat voor
    // de sync die hem daarna verwijderde. De Action leest hem onder de lock
    // opnieuw en schrijft niets.
    CompetitionMatch::query()->whereKey($match->id)->delete();

    expect(fn () => app(RecordMatchResult::class)->handle($match, 3, 0))
        ->toThrow(ModelNotFoundException::class);

    $this->assertModelMissing($match);
});

test('clearing a result that was already cleared changes nothing', function () {
    $competition = resultFixture();
    $match = $competition->matches()->first();
    markMatchPlayed($match, 3, 1);
    $stale = $match->fresh();

    // Iemand anders wiste de uitslag terwijl dit verzoek nog met de gespeelde
    // instantie werkte.
    CompetitionMatch::query()->whereKey($match->id)->update([
        'status' => MatchStatus::Pending->value,
        'first_player_score' => null,
        'second_player_score' => null,
    ]);

    expect(app(ClearMatchResult::class)->handle($stale))->toBeTrue();

    $unchanged = $match->fresh();

    expect($unchanged->status)->toBe(MatchStatus::Pending)
        ->and($unchanged->first_player_score)->toBeNull()
        ->and($unchanged->second_player_score)->toBeNull();
});

test('results cannot be changed on a non-active competition', function (string $state) {
    $competition = resultFixture();
    [$match, $pending] = $competition->matches()->get()->all();
    markMatchPlayed($match, 3, 1);

    $competition->update(['status' => CompetitionStatus::from($state)]);

    $this->put(route('competitions.matches.result.update', [$competition, $pending]), [
        'first_player_score' => 3,
        'second_player_score' => 0,
    ])->assertForbidden();
    $this->put(route('competitions.matches.result.update', [$competition, $match]), [
        'first_player_score' => 3,
        'second_player_score' => 0,
    ])->assertForbidden();
    $this->delete(route('competitions.matches.result.destroy', [$competition, $match]))->assertForbidden();

    $unchanged = $match->fresh();

    expect($unchanged->status)->toBe(MatchStatus::Played)
        ->and($unchanged->first_player_score)->toBe(3)
        ->and($unchanged->second_player_score)->toBe(1)
        ->and($pending->fresh()->status)->toBe(MatchStatus::Pending);
})->with(['draft', 'finished']);

test('a match of another competition gives a 404 on the result routes', function () {
    $competition = resultFixture();
    $other = resultFixture();
    $match = $other->matches()->first();
    markMatchPlayed($match, 3, 1);

    $this->put(route('competitions.matches.result.update', [$competition, $match]), [
        'first_player_score' => 3,
        'second_player_score' => 0,
    ])->assertNotFound();
    $this->delete(route('competitions.matches.result.destroy', [$competition, $match]))->assertNotFound();

    $unchanged = $match->fresh();

    expect($unchanged->status)->toBe(MatchStatus::Played)
        ->and($unchanged->second_player_score)->toBe(1);
});

test('participants cannot record or clear a result', function () {
    $competition = resultFixture();
    [$pending, $played] = $competition->matches()->get()->all();
    markMatchPlayed($played, 3, 1);

    // Bewust een deelnemer buiten deze competitie: een eigen deelnemer zonder
    // ingediende beschikbaarheid wordt door EnsureAvailabilityIsSubmitted al
    // eerder omgeleid, waardoor de beheerdersgrens zelf niet getest zou zijn.
    $this->actingAs(User::factory()->participant()->create());

    $this->put(route('competitions.matches.result.update', [$competition, $pending]), [
        'first_player_score' => 3,
        'second_player_score' => 0,
    ])->assertForbidden();
    $this->delete(route('competitions.matches.result.destroy', [$competition, $played]))->assertForbidden();

    expect($pending->fresh()->status)->toBe(MatchStatus::Pending)
        ->and($played->fresh()->status)->toBe(MatchStatus::Played);
});

test('guests are redirected to the login page from the result routes', function () {
    $competition = resultFixture();
    [$pending, $played] = $competition->matches()->get()->all();
    markMatchPlayed($played, 3, 1);

    auth()->logout();

    $this->put(route('competitions.matches.result.update', [$competition, $pending]), [
        'first_player_score' => 3,
        'second_player_score' => 0,
    ])->assertRedirect(route('login'));
    $this->delete(route('competitions.matches.result.destroy', [$competition, $played]))->assertRedirect(route('login'));

    expect($pending->fresh()->status)->toBe(MatchStatus::Pending)
        ->and($played->fresh()->status)->toBe(MatchStatus::Played);
});

test('the edit page tells whether results can be changed and which format applies', function () {
    $competition = resultFixture(bestOf: 7);

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('competition.allows_result_changes', true)
            ->where('matchFormat', [
                'best_of' => 7,
                'games_to_win' => 4,
                'is_locked' => false,
            ]),
        );
});

test('the edit page tells that results cannot be changed on a non-active competition', function (string $state) {
    $competition = resultFixture();
    $competition->update(['status' => CompetitionStatus::from($state)]);

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('competition.allows_result_changes', false),
        );
})->with(['draft', 'finished']);

test('the match format is reported locked once a match is played', function () {
    $competition = resultFixture();
    markMatchPlayed($competition->matches()->first(), 3, 1);

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('matchFormat.is_locked', true),
        );
});
