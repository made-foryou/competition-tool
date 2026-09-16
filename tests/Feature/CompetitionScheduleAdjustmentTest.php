<?php

use App\Actions\Competitions\MoveCompetitionMatch;
use App\Actions\Competitions\SyncCompetitionMatches;
use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Enums\SchedulingFailure;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\MatchDayField;
use App\Models\User;
use App\Support\CompetitionSettings;
use App\Support\Scheduling\ClockTime;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());
});

/**
 * Een actieve competitie met deelnemers, de bijbehorende round-robin, een
 * aantal speeldagen met tafels en voor iedere deelnemer beschikbaarheid op
 * iedere speeldag.
 *
 * Eigen naam en eigen kopie: Pest deelt één globale functienaamruimte over
 * alle testbestanden, dus `schedulingFixture` en `scheduleFixture` zijn al
 * bezet door de andere schematests.
 *
 * Bij 19:00-23:00 met de standaardinstellingen (20 minuten wedstrijd, 5
 * minuten wissel, 15 minuten pauze) levert dat negen slots op: 19:00, 19:25,
 * 19:50, 20:15, 20:40, pauze, 21:20, 21:45, 22:10 en 22:35.
 *
 * @param  array<string, mixed>  $settings
 */
function adjustmentFixture(int $players = 4, int $days = 2, int $fields = 2, array $settings = [], string $startsAt = '19:00', string $endsAt = '23:00'): Competition
{
    $competition = Competition::factory()
        ->withSettings(CompetitionSettings::fromArray($settings))
        ->create();

    $participants = User::factory()->participant()->count($players)->create();
    $competition->participants()->attach($participants->modelKeys());

    app(SyncCompetitionMatches::class)->handle($competition);

    for ($index = 0; $index < $days; $index++) {
        $matchDay = MatchDay::factory()
            ->withFields($fields)
            ->create([
                'competition_id' => $competition->id,
                'date' => now()->addWeek()->addDays($index)->toDateString(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

        foreach ($participants as $participant) {
            MatchDayAvailability::factory()->create([
                'match_day_id' => $matchDay->id,
                'user_id' => $participant->id,
            ]);
        }
    }

    return $competition->refresh();
}

/**
 * Zet een bestaande wedstrijd op een vaste plek. Via de query builder, omdat
 * de planningskolommen bewust niet fillable zijn.
 *
 * @param  array<string, mixed>  $attributes  extra kolommen, bijvoorbeeld de status of `pinned_at`
 */
function placeMatchOn(CompetitionMatch $match, MatchDay $matchDay, ?MatchDayField $field, string $startsAt, array $attributes = []): void
{
    $duration = $match->competition->settings->matchDurationMinutes;

    CompetitionMatch::query()
        ->whereKey($match->id)
        ->update(array_merge([
            'match_day_id' => $matchDay->id,
            'match_day_field_id' => $field?->id,
            'starts_at' => $startsAt.':00',
            'ends_at' => ClockTime::fromMinutes(ClockTime::toMinutes($startsAt) + $duration).':00',
            'scheduling_failure' => null,
        ], $attributes));
}

/**
 * De onderlinge wedstrijd van twee deelnemers. Het paar staat canoniek
 * opgeslagen (laagste user-id eerst), dus de ids gaan eerst op volgorde.
 */
function adjustmentMatchBetween(Competition $competition, User $first, User $second): CompetitionMatch
{
    $ids = [$first->id, $second->id];
    sort($ids);

    return $competition->matches()
        ->where('first_player_id', $ids[0])
        ->where('second_player_id', $ids[1])
        ->firstOrFail();
}

test('rescheduling clears pending unpinned matches and recomputes', function () {
    $competition = adjustmentFixture();
    $lastDay = $competition->matchDays()->get()->last();
    $match = $competition->matches()->first();

    // Bewust het allerlaatste slot van de laatste avond: daar zet de planner
    // zes wedstrijden over twee avonden nooit uit zichzelf neer.
    placeMatchOn($match, $lastDay, $lastDay->fields()->first(), '22:35');

    $this->post(route('competitions.schedule.rebuild', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __(':count matches scheduled.', ['count' => 6]),
        ]);

    $moved = $match->fresh();

    expect($moved->isScheduled())->toBeTrue()
        ->and([$moved->match_day_id, $moved->starts_at])->not->toBe([$lastDay->id, '22:35'])
        ->and($competition->matches()->get()->every(fn (CompetitionMatch $each): bool => $each->isScheduled()))->toBeTrue();
});

test('rescheduling keeps played and pinned matches in place', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $fields = $matchDay->fields()->orderBy('position')->get();
    $matches = $competition->matches()->orderBy('id')->get();

    placeMatchOn($matches[0], $matchDay, $fields[0], '22:35', ['status' => MatchStatus::Played->value]);
    placeMatchOn($matches[1], $matchDay, $fields[1], '22:35', ['pinned_at' => now()]);

    $this->post(route('competitions.schedule.rebuild', $competition))->assertRedirect();

    expect($matches[0]->fresh()->starts_at)->toBe('22:35')
        ->and($matches[0]->fresh()->match_day_field_id)->toBe($fields[0]->id)
        ->and($matches[1]->fresh()->starts_at)->toBe('22:35')
        ->and($matches[1]->fresh()->match_day_field_id)->toBe($fields[1]->id)
        ->and($matches[1]->fresh()->isPinned())->toBeTrue();
});

test('rescheduling mentions how many matches were kept', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $fields = $matchDay->fields()->orderBy('position')->get();
    $matches = $competition->matches()->orderBy('id')->get();

    placeMatchOn($matches[0], $matchDay, $fields[0], '22:35', ['status' => MatchStatus::Played->value]);
    placeMatchOn($matches[1], $matchDay, $fields[1], '22:35', ['pinned_at' => now()]);

    $this->post(route('competitions.schedule.rebuild', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __(':count matches scheduled.', ['count' => 4])
                .' '.__(':count played or pinned matches were kept in place.', ['count' => 2]),
        ]);

    // Zonder de vastgezette wedstrijd blijft alleen de gespeelde staan, en dat
    // is de enkelvoudsvorm van dezelfde zin.
    CompetitionMatch::query()->whereKey($matches[1]->id)->update(['pinned_at' => null]);

    $this->post(route('competitions.schedule.rebuild', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __(':count matches scheduled.', ['count' => 5])
                .' '.__(':count played or pinned match was kept in place.', ['count' => 1]),
        ]);
});

test('rescheduling is refused on a non-active competition', function (string $state) {
    $competition = adjustmentFixture();
    $competition->update(['status' => CompetitionStatus::from($state)]);

    $this->post(route('competitions.schedule.rebuild', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => __('Matches can only be scheduled for an active competition. Change the status under General.'),
        ]);

    expect($competition->matches()->whereNotNull('starts_at')->count())->toBe(0);
})->with(['draft', 'finished']);

test('rescheduling is blocked without match days', function () {
    $competition = adjustmentFixture(days: 0);

    $this->post(route('competitions.schedule.rebuild', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => __('Add match days before scheduling.'),
        ]);

    expect($competition->matches()->whereNotNull('starts_at')->count())->toBe(0);
});

test('rescheduling is rate limited', function () {
    $competition = adjustmentFixture();
    $signature = sha1((string) auth()->id());

    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->post(route('competitions.schedule.rebuild', $competition))->assertRedirect();
    }

    $this->post(route('competitions.schedule.rebuild', $competition))->assertStatus(429);

    // De teller hangt aan de ingelogde gebruiker en zou anders ook in een
    // volgende test nog meetellen.
    RateLimiter::clear($signature);
});

test('rescheduling keeps a pinned match that lies outside the current grid', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $field = $matchDay->fields()->orderBy('position')->first();
    $match = $competition->matches()->first();

    // 19:10 is geen slotgrens van het raster (19:00 in stappen van 25
    // minuten), maar de wedstrijd bezet wel 19:10-19:30 plus wisseltijd.
    placeMatchOn($match, $matchDay, $field, '19:10', ['pinned_at' => now()]);

    $this->post(route('competitions.schedule.rebuild', $competition))->assertRedirect();

    $kept = $match->fresh();

    expect($kept->starts_at)->toBe('19:10')
        ->and($kept->match_day_field_id)->toBe($field->id)
        ->and($kept->isPinned())->toBeTrue();

    // En hij blokkeert zijn tafel: de slots 19:00 en 19:25 overlappen met
    // 19:10-19:35, dus daar mag niets anders op deze tafel staan.
    $overlapping = $competition->matches()
        ->whereKeyNot($match->id)
        ->where('match_day_field_id', $field->id)
        ->whereIn('starts_at', ['19:00:00', '19:25:00'])
        ->count();

    expect($overlapping)->toBe(0);
});

test('an admin can move a match to another field and slot and it becomes pinned', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $field = $matchDay->fields()->orderByDesc('position')->first();
    $match = $competition->matches()->first();

    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $field->id,
        'starts_at' => '20:40',
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __('Match moved and pinned.'),
        ]);

    $moved = $match->fresh();

    expect($moved->match_day_id)->toBe($matchDay->id)
        ->and($moved->match_day_field_id)->toBe($field->id)
        ->and($moved->starts_at)->toBe('20:40')
        ->and($moved->ends_at)->toBe('21:00')
        ->and($moved->isPinned())->toBeTrue();
});

test('moving a match onto an occupied field is rejected', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $field = $matchDay->fields()->orderBy('position')->first();
    $players = $competition->participants()->orderBy('users.id')->get();

    // Twee wedstrijden zonder gedeelde speler, zodat alleen de tafel in de weg
    // zit en niet de spelersbezetting.
    $occupier = adjustmentMatchBetween($competition, $players[0], $players[1]);
    $match = adjustmentMatchBetween($competition, $players[2], $players[3]);

    placeMatchOn($occupier, $matchDay, $field, '19:00');

    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $field->id,
        'starts_at' => '19:00',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['match_day_field_id' => __('This field is already taken at this time.')]);

    expect($match->fresh()->isScheduled())->toBeFalse();
});

test('moving a match to a slot where a player is busy is rejected', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $fields = $matchDay->fields()->orderBy('position')->get();
    $players = $competition->participants()->orderBy('users.id')->get();

    $busy = adjustmentMatchBetween($competition, $players[0], $players[1]);
    $match = adjustmentMatchBetween($competition, $players[0], $players[2]);

    placeMatchOn($busy, $matchDay, $fields[0], '19:00');

    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $fields[1]->id,
        'starts_at' => '19:00',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors([
            'starts_at' => __(':player already plays another match at this time.', ['player' => $players[0]->display_name]),
        ]);

    expect($match->fresh()->isScheduled())->toBeFalse();
});

test('moving a match to a match day where a player is unavailable is rejected', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->get()->last();
    $players = $competition->participants()->orderBy('users.id')->get();

    $match = adjustmentMatchBetween($competition, $players[0], $players[1]);

    MatchDayAvailability::query()
        ->where('match_day_id', $matchDay->id)
        ->where('user_id', $players[0]->id)
        ->delete();

    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $matchDay->fields()->first()->id,
        'starts_at' => '19:00',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors([
            'match_day_id' => __(':player is not available on this match day.', ['player' => $players[0]->display_name]),
        ]);

    expect($match->fresh()->isScheduled())->toBeFalse();
});

test('moving a match outside the opening hours is rejected', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $match = $competition->matches()->first();

    // 19:10 is een geldige kloktijd maar geen slotgrens: het raster loopt van
    // 19:00 in stappen van 25 minuten.
    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $matchDay->fields()->first()->id,
        'starts_at' => '19:10',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['starts_at' => __('This time is not a slot within the opening hours of this match day.')]);

    expect($match->fresh()->isScheduled())->toBeFalse();
});

test('moving a match beyond the daily maximum is rejected', function () {
    $competition = adjustmentFixture(settings: ['max_matches_per_player_per_day' => 1]);
    $matchDay = $competition->matchDays()->first();
    $fields = $matchDay->fields()->orderBy('position')->get();
    $players = $competition->participants()->orderBy('users.id')->get();

    $first = adjustmentMatchBetween($competition, $players[0], $players[1]);
    $match = adjustmentMatchBetween($competition, $players[0], $players[2]);

    placeMatchOn($first, $matchDay, $fields[0], '19:00');

    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $fields[1]->id,
        'starts_at' => '19:50',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors([
            'match_day_id' => __(':player already plays the maximum number of matches on this match day.', ['player' => $players[0]->display_name]),
        ]);

    expect($match->fresh()->isScheduled())->toBeFalse();
});

test('moving a match to a field of another match day is rejected', function () {
    $competition = adjustmentFixture();
    $matchDays = $competition->matchDays()->get();
    $match = $competition->matches()->first();

    // Afwijking van de andere schendingstests: deze schending komt over HTTP
    // nooit bij de Action aan, omdat `MoveMatchRequest` de combinatie speeldag
    // + tafel al met `Rule::exists` afwijst (zie de test hieronder over de
    // vormvalidatie). De guard in de Action bewaakt de directe aanroep en
    // wordt daarom hier direct getest.
    $move = fn () => app(MoveCompetitionMatch::class)->handle(
        $match,
        $matchDays[0]->id,
        $matchDays[1]->fields()->first()->id,
        '19:00',
    );

    expect($move)->toThrow(
        ValidationException::class,
        __('This field does not belong to the selected match day.'),
    );

    expect($match->fresh()->isScheduled())->toBeFalse();
});

test('moving a match with too little rest is allowed and warns', function () {
    $competition = adjustmentFixture(settings: ['min_rest_minutes' => 10]);
    $matchDay = $competition->matchDays()->first();
    $fields = $matchDay->fields()->orderBy('position')->get();
    $players = $competition->participants()->orderBy('users.id')->get();

    $first = adjustmentMatchBetween($competition, $players[0], $players[1]);
    $match = adjustmentMatchBetween($competition, $players[0], $players[2]);

    // 19:00-19:20 en daarna 19:25: vijf minuten rust waar er tien nodig zijn.
    placeMatchOn($first, $matchDay, $fields[0], '19:00');

    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $fields[1]->id,
        'starts_at' => '19:25',
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'warning',
            'message' => __('Match moved and pinned. Note: the minimum rest time is not met.'),
        ]);

    expect($match->fresh()->starts_at)->toBe('19:25')
        ->and($match->fresh()->isPinned())->toBeTrue();
});

test('a played match cannot be moved', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $fields = $matchDay->fields()->orderBy('position')->get();
    $match = $competition->matches()->first();

    placeMatchOn($match, $matchDay, $fields[0], '19:00', ['status' => MatchStatus::Played->value]);

    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $fields[1]->id,
        'starts_at' => '19:50',
    ])->assertForbidden();

    expect($match->fresh()->starts_at)->toBe('19:00')
        ->and($match->fresh()->match_day_field_id)->toBe($fields[0]->id);
});

test('moving a match of another competition gives a 404', function () {
    $competition = adjustmentFixture();
    $other = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $match = $other->matches()->first();

    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $matchDay->fields()->first()->id,
        'starts_at' => '19:00',
    ])->assertNotFound();

    expect($match->fresh()->isScheduled())->toBeFalse();
});

test('moving is refused on a non-active competition', function (string $state) {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $match = $competition->matches()->first();

    $competition->update(['status' => CompetitionStatus::from($state)]);

    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $matchDay->fields()->first()->id,
        'starts_at' => '19:00',
    ])->assertForbidden();

    expect($match->fresh()->isScheduled())->toBeFalse();
})->with(['draft', 'finished']);

test('form validation rejects a field from another match day', function () {
    $competition = adjustmentFixture();
    $matchDays = $competition->matchDays()->get();
    $match = $competition->matches()->first();

    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDays[0]->id,
        'match_day_field_id' => $matchDays[1]->fields()->first()->id,
        'starts_at' => '19:00',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['match_day_field_id' => __('This field does not belong to the selected match day.')]);

    expect($match->fresh()->isScheduled())->toBeFalse();
});

test('form validation rejects a start time in the wrong format', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $match = $competition->matches()->first();

    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $matchDay->fields()->first()->id,
        'starts_at' => '19h00',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('starts_at');

    expect($match->fresh()->isScheduled())->toBeFalse();
});

test('an array start time is rejected with a validation error instead of a server error', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $match = $competition->matches()->first();

    // `prepareForValidation()` werkt op ongevalideerde invoer: zonder
    // typecontrole liep een array daar op een "Array to string conversion" en
    // dus op een 500 in plaats van een nette veldfout.
    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $matchDay->fields()->first()->id,
        'starts_at' => ['19:00'],
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('starts_at');

    expect($match->fresh()->isScheduled())->toBeFalse();
});

test('a match can be moved onto the slot it already occupies', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $fields = $matchDay->fields()->orderBy('position')->get();
    $match = $competition->matches()->first();

    placeMatchOn($match, $matchDay, $fields[0], '19:00');

    // De wedstrijd bezet zijn eigen plek; zonder `excludeMatchId` in de
    // context zou hij zichzelf als bezetting tegenkomen en zichzelf blokkeren.
    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $fields[0]->id,
        'starts_at' => '19:00',
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($match->fresh()->starts_at)->toBe('19:00')
        ->and($match->fresh()->match_day_field_id)->toBe($fields[0]->id)
        ->and($match->fresh()->isPinned())->toBeTrue();

    // Hetzelfde slot, andere tafel: ook dan zit alleen de eigen bezetting in
    // de weg.
    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $fields[1]->id,
        'starts_at' => '19:00',
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($match->fresh()->starts_at)->toBe('19:00')
        ->and($match->fresh()->match_day_field_id)->toBe($fields[1]->id);
});

test('a match that got a result after the request started is not moved', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $field = $matchDay->fields()->first();
    $match = $competition->matches()->first();

    // De Form Request keek vóór de rijlock naar de status; deze test staat
    // voor de wedstrijd die daarna een uitslag kreeg. De Action leest hem
    // onder de lock opnieuw en weigert alsnog.
    CompetitionMatch::query()->whereKey($match->id)->update(['status' => MatchStatus::Played->value]);

    $move = fn () => app(MoveCompetitionMatch::class)->handle($match, $matchDay->id, $field->id, '19:00');

    expect($move)->toThrow(
        ValidationException::class,
        __('This match already has a result and cannot be moved.'),
    );

    expect($match->fresh()->isScheduled())->toBeFalse();
});

test('an unscheduled match can be placed from the report and its failure reason is cleared', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $field = $matchDay->fields()->first();
    $match = $competition->matches()->first();

    CompetitionMatch::query()
        ->whereKey($match->id)
        ->update(['scheduling_failure' => SchedulingFailure::NoCapacity->value]);

    // Het rapport stuurt exact dezelfde velden; alleen staat de wedstrijd nog
    // nergens, dus er is geen huidige speeldag om vanuit te vertrekken.
    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $field->id,
        'starts_at' => '19:00',
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $placed = $match->fresh();

    expect($placed->isScheduled())->toBeTrue()
        ->and($placed->isPinned())->toBeTrue()
        ->and($placed->scheduling_failure)->toBeNull();
});

test('a scheduled match can be pinned and unpinned', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $match = $competition->matches()->first();

    placeMatchOn($match, $matchDay, $matchDay->fields()->first(), '19:00');

    $this->post(route('competitions.matches.pin.store', [$competition, $match]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Match pinned.')]);

    expect($match->fresh()->isPinned())->toBeTrue();

    $this->delete(route('competitions.matches.pin.destroy', [$competition, $match]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Match unpinned.')]);

    expect($match->fresh()->isPinned())->toBeFalse();
});

test('an unscheduled match cannot be pinned', function () {
    $competition = adjustmentFixture();
    $match = $competition->matches()->first();

    $this->post(route('competitions.matches.pin.store', [$competition, $match]))
        ->assertStatus(422);

    expect($match->fresh()->isPinned())->toBeFalse();
});

test('a played match cannot be pinned', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $match = $competition->matches()->first();

    placeMatchOn($match, $matchDay, $matchDay->fields()->first(), '19:00', ['status' => MatchStatus::Played->value]);

    $this->post(route('competitions.matches.pin.store', [$competition, $match]))
        ->assertForbidden();

    expect($match->fresh()->isPinned())->toBeFalse();
});

test('a match of another competition gives a 404 on the pin routes', function () {
    $competition = adjustmentFixture();
    $other = adjustmentFixture();
    $matchDay = $other->matchDays()->first();
    $match = $other->matches()->first();

    // Op zijn eigen competitie zou deze wedstrijd wél vastgezet kunnen worden;
    // de scoped binding op `Competition::matches()` moet hem hier afwijzen.
    placeMatchOn($match, $matchDay, $matchDay->fields()->first(), '19:00');

    $this->post(route('competitions.matches.pin.store', [$competition, $match]))->assertNotFound();
    $this->delete(route('competitions.matches.pin.destroy', [$competition, $match]))->assertNotFound();

    expect($match->fresh()->isPinned())->toBeFalse();
});

test('pinning is refused on a non-active competition', function (string $state) {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $match = $competition->matches()->first();

    placeMatchOn($match, $matchDay, $matchDay->fields()->first(), '19:00');
    $competition->update(['status' => CompetitionStatus::from($state)]);

    $this->post(route('competitions.matches.pin.store', [$competition, $match]))->assertForbidden();
    $this->delete(route('competitions.matches.pin.destroy', [$competition, $match]))->assertForbidden();

    expect($match->fresh()->isPinned())->toBeFalse();
})->with(['draft', 'finished']);

test('an unpinned match is moved by the next reschedule', function () {
    // Eén speeldag en drie spelers, zodat de uitkomst van de planner volledig
    // vastligt: de vastgezette wedstrijd houdt het eerste slot en de
    // losgemaakte wedstrijd van dezelfde speler kan alleen verderop op de
    // avond terecht -- in elk geval niet meer op zijn oude plek.
    $competition = adjustmentFixture(players: 3, days: 1);
    $matchDay = $competition->matchDays()->first();
    $field = $matchDay->fields()->first();
    $players = $competition->participants()->orderBy('users.id')->get();

    $stays = adjustmentMatchBetween($competition, $players[0], $players[1]);
    $released = adjustmentMatchBetween($competition, $players[0], $players[2]);

    placeMatchOn($stays, $matchDay, $field, '19:00');
    placeMatchOn($released, $matchDay, $field, '22:35');

    $this->post(route('competitions.matches.pin.store', [$competition, $stays]))->assertRedirect();
    $this->post(route('competitions.matches.pin.store', [$competition, $released]))->assertRedirect();
    $this->delete(route('competitions.matches.pin.destroy', [$competition, $released]))->assertRedirect();

    expect($stays->fresh()->isPinned())->toBeTrue()
        ->and($released->fresh()->isPinned())->toBeFalse();

    $this->post(route('competitions.schedule.rebuild', $competition))->assertRedirect();

    expect($stays->fresh()->starts_at)->toBe('19:00')
        ->and($stays->fresh()->match_day_field_id)->toBe($field->id)
        ->and($released->fresh()->isScheduled())->toBeTrue()
        ->and($released->fresh()->starts_at)->not->toBe('22:35');
});

test('participants cannot move pin or reschedule', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $match = $competition->matches()->first();

    // Bewust een deelnemer buiten deze competitie: een eigen deelnemer zonder
    // ingediende beschikbaarheid wordt door EnsureAvailabilityIsSubmitted al
    // eerder omgeleid, waardoor de beheerdersgrens zelf niet getest zou zijn.
    $this->actingAs(User::factory()->participant()->create());

    $this->post(route('competitions.schedule.rebuild', $competition))->assertForbidden();
    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $matchDay->fields()->first()->id,
        'starts_at' => '19:00',
    ])->assertForbidden();
    $this->post(route('competitions.matches.pin.store', [$competition, $match]))->assertForbidden();
    $this->delete(route('competitions.matches.pin.destroy', [$competition, $match]))->assertForbidden();

    expect($match->fresh()->isScheduled())->toBeFalse()
        ->and($match->fresh()->isPinned())->toBeFalse();
});

test('guests are redirected to the login page', function () {
    $competition = adjustmentFixture();
    $matchDay = $competition->matchDays()->first();
    $match = $competition->matches()->first();

    auth()->logout();

    $this->post(route('competitions.schedule.rebuild', $competition))->assertRedirect(route('login'));
    $this->put(route('competitions.matches.schedule.update', [$competition, $match]), [
        'match_day_id' => $matchDay->id,
        'match_day_field_id' => $matchDay->fields()->first()->id,
        'starts_at' => '19:00',
    ])->assertRedirect(route('login'));
    $this->post(route('competitions.matches.pin.store', [$competition, $match]))->assertRedirect(route('login'));
    $this->delete(route('competitions.matches.pin.destroy', [$competition, $match]))->assertRedirect(route('login'));

    expect($match->fresh()->isScheduled())->toBeFalse()
        ->and($match->fresh()->isPinned())->toBeFalse();
});
