<?php

use App\Actions\Competitions\ScheduleCompetitionMatches;
use App\Actions\Competitions\SyncCompetitionMatches;
use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Enums\SchedulingFailure;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;
use App\Support\CompetitionSettings;
use App\Support\Scheduling\ClockTime;
use App\Support\Scheduling\SlotGrid;
use Database\Factories\MatchDayFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());
});

/**
 * Een actieve competitie met deelnemers, de bijbehorende round-robin, een
 * aantal speeldagen met tafels en voor iedere deelnemer beschikbaarheid op
 * iedere speeldag.
 *
 * Een compacte kopie van de fixture in `CompetitionScheduleTest`: Feature-tests
 * delen in dit project geen helperbestand, dus elk bestand brengt zijn eigen
 * opzet mee.
 *
 * @param  array<string, mixed>  $settings
 */
function schedulingFixture(int $players = 4, int $days = 2, int $fields = 2, array $settings = [], string $startsAt = '19:00', string $endsAt = '23:00'): Competition
{
    $competition = Competition::factory()
        ->withSettings(CompetitionSettings::fromArray($settings))
        ->create();

    $participants = User::factory()->participant()->count($players)->create();
    $competition->participants()->attach($participants->modelKeys());

    app(SyncCompetitionMatches::class)->handle($competition);

    for ($index = 0; $index < $days; $index++) {
        $matchDay = MatchDay::factory()
            ->when($fields > 0, fn (MatchDayFactory $factory) => $factory->withFields($fields))
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
function placeMatch(CompetitionMatch $match, MatchDay $matchDay, ?int $fieldId, string $startsAt, array $attributes = []): void
{
    $duration = $match->competition->settings->matchDurationMinutes;

    CompetitionMatch::query()
        ->whereKey($match->id)
        ->update(array_merge([
            'match_day_id' => $matchDay->id,
            'match_day_field_id' => $fieldId,
            'starts_at' => $startsAt.':00',
            'ends_at' => ClockTime::fromMinutes(ClockTime::toMinutes($startsAt) + $duration).':00',
        ], $attributes));
}

/**
 * Een competitie die precies op de gegeven reden vastloopt, of op geen enkele
 * (`null`). De volgorde van de precondities maakt dat elke variant het
 * minimale weglaat dat nodig is om júist daar te stranden.
 */
function competitionBlockedBy(?string $reason): Competition
{
    return match ($reason) {
        'inactive' => tap(schedulingFixture(), fn (Competition $competition) => $competition->update(['status' => CompetitionStatus::Draft])),
        'no_match_days' => schedulingFixture(days: 0),
        'no_fields' => schedulingFixture(fields: 0),
        'no_availability' => tap(schedulingFixture(), function (Competition $competition): void {
            MatchDayAvailability::query()
                ->whereIn('match_day_id', $competition->matchDays()->select('id'))
                ->delete();
        }),
        // Eén deelnemer levert wel beschikbaarheid maar geen enkele wedstrijd
        // op, dus de eerste drie precondities zijn voldaan.
        'no_matches' => schedulingFixture(players: 1),
        'nothing_to_schedule' => tap(schedulingFixture(), fn (Competition $competition) => app(ScheduleCompetitionMatches::class)->handle($competition)),
        default => schedulingFixture(),
    };
}

/**
 * De toast-tekst die de controller bij die reden hoort te tonen.
 */
function schedulingBlockerMessage(string $reason): string
{
    return match ($reason) {
        'no_match_days' => __('Add match days before scheduling.'),
        'no_fields' => __('Add fields to at least one match day before scheduling.'),
        'no_availability' => __('Nobody has filled in their availability yet.'),
        'no_matches' => __('There are no matches to schedule.'),
        default => __('All matches are already scheduled.'),
    };
}

test('admins can schedule the matches of an active competition', function () {
    $competition = schedulingFixture();

    $this->post(route('competitions.schedule.store', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __(':count matches scheduled.', ['count' => 6]),
        ]);

    expect($competition->matches()->get()->every(fn (CompetitionMatch $match): bool => $match->isScheduled()))->toBeTrue();
});

test('a single scheduled match uses the singular toast', function () {
    $competition = schedulingFixture(players: 2);

    $this->post(route('competitions.schedule.store', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __(':count match scheduled.', ['count' => 1]),
        ]);

    expect($competition->matches()->count())->toBe(1)
        ->and($competition->matches()->first()->isScheduled())->toBeTrue();
});

test('a partial result flashes a warning with both counts', function () {
    $competition = schedulingFixture();

    // Eén deelnemer is op geen enkele avond beschikbaar: zijn drie
    // wedstrijden kunnen nergens heen, de andere drie wel.
    $unavailable = $competition->participants()->first();
    MatchDayAvailability::query()->where('user_id', $unavailable->id)->delete();

    $this->post(route('competitions.schedule.store', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'warning',
            'message' => __(':scheduled matches scheduled, :unscheduled matches could not be scheduled. See the planning report below.', [
                'scheduled' => 3,
                'unscheduled' => 3,
            ]),
        ]);

    expect($competition->matches()->whereNotNull('starts_at')->count())->toBe(3)
        ->and($competition->matches()->where('scheduling_failure', SchedulingFailure::NoSharedMatchDay->value)->count())->toBe(3);
});

test('a partial result with one match on each side uses both singulars', function () {
    $competition = schedulingFixture(players: 3);
    $participants = $competition->participants()->orderBy('id')->get();
    $matchDay = $competition->matchDays()->first();

    // De onderlinge wedstrijd van de eerste twee spelers staat al op het
    // schema, dus de planner houdt er precies twee over.
    $placed = $competition->matches()
        ->whereIn('first_player_id', [$participants[0]->id, $participants[1]->id])
        ->whereIn('second_player_id', [$participants[0]->id, $participants[1]->id])
        ->first();

    placeMatch($placed, $matchDay, $matchDay->fields()->first()->id, '19:00');

    // De tweede speler is op geen enkele avond beschikbaar: zijn wedstrijd
    // tegen de derde kan nergens heen, die van de eerste tegen de derde wel.
    MatchDayAvailability::query()->where('user_id', $participants[1]->id)->delete();

    $this->post(route('competitions.schedule.store', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'warning',
            'message' => __(':scheduled match scheduled, :unscheduled match could not be scheduled. See the planning report below.', [
                'scheduled' => 1,
                'unscheduled' => 1,
            ]),
        ]);
});

test('scheduling is refused with a toast on a non-active competition', function (string $state) {
    $competition = schedulingFixture();
    $competition->update(['status' => CompetitionStatus::from($state)]);

    $this->post(route('competitions.schedule.store', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => __('Matches can only be scheduled for an active competition. Change the status under General.'),
        ]);

    expect($competition->matches()->whereNotNull('starts_at')->count())->toBe(0);
})->with(['draft', 'finished']);

test('a blocked run flashes the blocker message', function (string $reason) {
    $competition = competitionBlockedBy($reason);
    $scheduledBefore = $competition->matches()->whereNotNull('starts_at')->count();

    $this->post(route('competitions.schedule.store', $competition))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => $reason === 'nothing_to_schedule' ? 'info' : 'error',
            'message' => schedulingBlockerMessage($reason),
        ]);

    expect($competition->matches()->whereNotNull('starts_at')->count())->toBe($scheduledBefore);
})->with(['no_match_days', 'no_fields', 'no_availability', 'no_matches', 'nothing_to_schedule']);

test('the edit page defers the schedule props and exposes the blocker in the same order as the controller', function (?string $reason) {
    $competition = competitionBlockedBy($reason);

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('competitions/edit')
            ->missing('schedule')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('schedule.blocked_reason', $reason)
                ->where('schedule.can_schedule', $reason === null),
            ),
        );
})->with([
    'inactive',
    'no_match_days',
    'no_fields',
    'no_availability',
    'no_matches',
    'nothing_to_schedule',
    null,
]);

test('the schedule props contain the same slot grid as the planner uses', function () {
    $competition = schedulingFixture();
    $this->post(route('competitions.schedule.store', $competition));

    $matchDay = $competition->matchDays()->first();
    $grid = SlotGrid::for($matchDay->starts_at, $matchDay->ends_at, $competition->settings);

    $expectedSlots = array_map(fn ($slot): array => [
        'index' => $slot->index,
        'starts_at' => $slot->startsAt(),
        'ends_at' => ClockTime::fromMinutes($grid->matchEndMinute($slot)),
    ], $grid->slots);

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('schedule.match_days.0.id', $matchDay->id)
                ->where('schedule.match_days.0.slots', $expectedSlots)
                ->where('schedule.match_days.0.break', [
                    'starts_at' => $grid->breakStartsAt(),
                    'ends_at' => $grid->breakEndsAt(),
                ])
                // Elke geplande wedstrijd wijst naar het slot waarop zijn
                // begintijd valt; het scherm rekent dus niets zelf uit.
                ->where('schedule.match_days.0.matches', fn (Collection $matches): bool => $matches->isNotEmpty()
                    && $matches->every(fn (array $match): bool => $match['slot_index'] === $grid->slotAt($match['starts_at'])?->index)),
            ),
        );
});

test('an off-grid match is exposed with a null slot index', function () {
    $competition = schedulingFixture();
    $matchDay = $competition->matchDays()->first();
    $field = $matchDay->fields()->first();

    // 19:10 ligt tussen twee slotgrenzen in: zo staat een gespeelde wedstrijd
    // erbij nadat de instellingen of de openingstijden zijn gewijzigd.
    placeMatch($competition->matches()->first(), $matchDay, $field->id, '19:10', ['status' => MatchStatus::Played->value]);

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('schedule.match_days.0.matches', 1)
                ->where('schedule.match_days.0.matches.0.starts_at', '19:10')
                ->where('schedule.match_days.0.matches.0.slot_index', null),
            ),
        );
});

test('a match without a field keeps its slot index so the grid can list it', function () {
    $competition = schedulingFixture();
    $matchDay = $competition->matchDays()->first();

    // Zo staat een gespeelde wedstrijd erbij nadat zijn tafel is verwijderd:
    // een geldige begintijd, maar geen tafel meer. Het scherm moet hem in de
    // lijst onder het grid tonen in plaats van hem te laten verdwijnen.
    placeMatch($competition->matches()->first(), $matchDay, null, '19:00', ['status' => MatchStatus::Played->value]);

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('schedule.match_days.0.matches', 1)
                ->where('schedule.match_days.0.matches.0.field_id', null)
                ->where('schedule.match_days.0.matches.0.slot_index', 0),
            ),
        );
});

test('rest violations are derived from the stored times', function () {
    $competition = schedulingFixture(settings: ['min_rest_minutes' => 10]);
    $matchDay = $competition->matchDays()->first();
    $fields = $matchDay->fields()->get();

    // Twee wedstrijden van dezelfde speler, met vijf minuten ertussen terwijl
    // er tien nodig zijn.
    $first = $competition->matches()->first();
    $second = $competition->matches()->where('id', '>', $first->id)
        ->where(fn ($query) => $query->where('first_player_id', $first->first_player_id)->orWhere('second_player_id', $first->first_player_id))
        ->first();

    placeMatch($first, $matchDay, $fields[0]->id, '19:00');
    placeMatch($second, $matchDay, $fields[1]->id, '19:25');

    $player = $first->firstPlayer->display_name;

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('schedule.rest_violations', [
                    ['match_id' => $second->id, 'player' => $player, 'gap_minutes' => 5, 'overlapping' => false],
                ]),
            ),
        );

    // Met voldoende rust verdwijnt de melding.
    placeMatch($second, $matchDay, $fields[1]->id, '19:35');

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('schedule.rest_violations', [])),
        );
});

test('two overlapping matches are reported as an overlap instead of a negative gap', function () {
    $competition = schedulingFixture();
    $matchDay = $competition->matchDays()->first();
    $fields = $matchDay->fields()->get();

    $first = $competition->matches()->first();
    $second = $competition->matches()->where('id', '>', $first->id)
        ->where(fn ($query) => $query->where('first_player_id', $first->first_player_id)->orWhere('second_player_id', $first->first_player_id))
        ->first();

    // 19:00-19:20 en 19:10-19:30 overlappen elkaar tien minuten.
    placeMatch($first, $matchDay, $fields[0]->id, '19:00');
    placeMatch($second, $matchDay, $fields[1]->id, '19:10');

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('schedule.rest_violations', [
                    [
                        'match_id' => $second->id,
                        'player' => $first->firstPlayer->display_name,
                        'gap_minutes' => 0,
                        'overlapping' => true,
                    ],
                ]),
            ),
        );
});

test('the summary counts total scheduled unscheduled played and pinned', function () {
    $competition = schedulingFixture();
    $matchDay = $competition->matchDays()->first();
    $fields = $matchDay->fields()->get();
    $matches = $competition->matches()->get();

    placeMatch($matches[0], $matchDay, $fields[0]->id, '19:00', ['status' => MatchStatus::Played->value]);
    placeMatch($matches[1], $matchDay, $fields[1]->id, '19:00', ['pinned_at' => now()]);
    placeMatch($matches[2], $matchDay, $fields[0]->id, '19:25');

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('schedule.summary', [
                    'total' => 6,
                    'scheduled' => 3,
                    'unscheduled' => 3,
                    'played' => 1,
                    'pinned' => 1,
                ]),
            ),
        );
});

test('the unscheduled list carries the stored failure reason', function () {
    $competition = schedulingFixture();
    $matches = $competition->matches()->get();

    CompetitionMatch::query()
        ->whereKey($matches[0]->id)
        ->update(['scheduling_failure' => SchedulingFailure::NoCapacity->value]);

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('schedule.unscheduled', 6)
                ->where('schedule.unscheduled.0.id', $matches[0]->id)
                ->where('schedule.unscheduled.0.first_player', $matches[0]->firstPlayer->display_name)
                ->where('schedule.unscheduled.0.second_player', $matches[0]->secondPlayer->display_name)
                ->where('schedule.unscheduled.0.reason', SchedulingFailure::NoCapacity->value)
                // Een wedstrijd die de planner nog nooit heeft geprobeerd,
                // heeft geen reden.
                ->where('schedule.unscheduled.1.reason', null),
            ),
        );
});

test('match rows expose match day field and time', function () {
    $competition = schedulingFixture();
    $matchDay = $competition->matchDays()->first();
    $field = $matchDay->fields()->first();
    $match = $competition->matches()->first();

    placeMatch($match, $matchDay, $field->id, '19:00', ['pinned_at' => now()]);

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('matches.0.match_day_date', $matchDay->date->toDateString())
                ->where('matches.0.field_name', $field->name)
                ->where('matches.0.starts_at', '19:00')
                ->where('matches.0.ends_at', '19:20')
                ->where('matches.0.is_pinned', true)
                ->where('matches.0.scheduling_failure', null)
                ->where('matches.1.match_day_date', null)
                ->where('matches.1.field_name', null)
                ->where('matches.1.starts_at', null)
                ->where('matches.1.is_pinned', false),
            ),
        );
});

test('scheduling is rate limited', function () {
    $competition = schedulingFixture();
    $signature = sha1((string) auth()->id());

    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->post(route('competitions.schedule.store', $competition))->assertRedirect();
    }

    $this->post(route('competitions.schedule.store', $competition))->assertStatus(429);

    // De teller hangt aan de ingelogde gebruiker en zou anders ook in een
    // volgende test nog meetellen.
    RateLimiter::clear($signature);
});

test('participants cannot schedule matches', function () {
    $competition = schedulingFixture();

    // Bewust een deelnemer buiten deze competitie: een eigen deelnemer zonder
    // ingediende beschikbaarheid wordt door EnsureAvailabilityIsSubmitted al
    // eerder omgeleid, waardoor de beheerdersgrens zelf niet getest zou zijn.
    $this->actingAs(User::factory()->participant()->create())
        ->post(route('competitions.schedule.store', $competition))
        ->assertForbidden();

    expect($competition->matches()->whereNotNull('starts_at')->count())->toBe(0);
});

test('guests are redirected to the login page', function () {
    $competition = schedulingFixture();

    auth()->logout();

    $this->post(route('competitions.schedule.store', $competition))
        ->assertRedirect(route('login'));

    expect($competition->matches()->whereNotNull('starts_at')->count())->toBe(0);
});
