<?php

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\MatchDayField;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());

    $this->competition = Competition::factory()->create([
        'starts_at' => '2026-10-01',
        'ends_at' => '2026-10-10',
    ]);
});

test('admins can add a match day with automatically generated fields', function () {
    $editUrl = route('competitions.edit', $this->competition).'?tab=match-days';

    $this->from($editUrl)
        ->post(route('competitions.match-days.store', $this->competition), [
            'date' => '2026-10-02',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'field_count' => 3,
        ])->assertRedirect($editUrl)
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Match day added.')]);

    $matchDay = MatchDay::query()->firstOrFail();

    expect($matchDay->competition_id)->toBe($this->competition->id)
        ->and($matchDay->date->toDateString())->toBe('2026-10-02')
        ->and($matchDay->fields()->pluck('name')->all())->toBe(['Tafel 1', 'Tafel 2', 'Tafel 3'])
        ->and($matchDay->fields()->pluck('position')->all())->toBe([1, 2, 3]);
});

test('the stored times are read back as H:i', function () {
    $this->post(route('competitions.match-days.store', $this->competition), [
        'date' => '2026-10-02',
        'starts_at' => '09:00',
        'ends_at' => '17:30',
        'field_count' => 1,
    ]);

    $matchDay = MatchDay::query()->firstOrFail();

    expect($matchDay->starts_at)->toBe('09:00')
        ->and($matchDay->ends_at)->toBe('17:30')
        ->and(DB::table('match_days')->value('starts_at'))->toBe('09:00:00');
});

test('the match day must fall within the competition period', function (string $date) {
    $this->post(route('competitions.match-days.store', $this->competition), [
        'date' => $date,
        'starts_at' => '09:00',
        'ends_at' => '17:00',
        'field_count' => 2,
    ])->assertSessionHasErrors('date');
})->with(['2026-09-30', '2026-10-11']);

test('the end time must be after the start time', function () {
    $this->post(route('competitions.match-days.store', $this->competition), [
        'date' => '2026-10-02',
        'starts_at' => '17:00',
        'ends_at' => '09:00',
        'field_count' => 2,
    ])->assertSessionHasErrors('ends_at');
});

test('the number of fields must be between one and the maximum', function (int $count) {
    $this->post(route('competitions.match-days.store', $this->competition), [
        'date' => '2026-10-02',
        'starts_at' => '09:00',
        'ends_at' => '17:00',
        'field_count' => $count,
    ])->assertSessionHasErrors('field_count');
})->with([0, MatchDay::MAX_FIELDS + 1]);

test('the edit page shows the match day and its fields', function () {
    $matchDay = MatchDay::factory()
        ->withFields(3)
        ->create(['competition_id' => $this->competition, 'date' => '2026-10-02']);

    $this->get(route('competitions.match-days.edit', [$this->competition, $matchDay]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('competitions/match-days/edit')
            ->has('fields', 3)
            ->where('matchDay.starts_at', '09:00')
            ->where('matchDay.date', '2026-10-02'),
        );
});

test('admins can update the date and times of a match day', function () {
    $matchDay = MatchDay::factory()->create(['competition_id' => $this->competition]);

    $this->put(route('competitions.match-days.update', [$this->competition, $matchDay]), [
        'date' => '2026-10-05',
        'starts_at' => '10:15',
        'ends_at' => '16:45',
    ])->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Match day moved.')]);

    $matchDay->refresh();

    expect($matchDay->date->toDateString())->toBe('2026-10-05')
        ->and($matchDay->starts_at)->toBe('10:15')
        ->and($matchDay->ends_at)->toBe('16:45');
});

test('changing only the times keeps the availability and the schedule of the match day', function () {
    $matchDay = MatchDay::factory()->create(['competition_id' => $this->competition, 'date' => '2026-10-02']);
    $field = MatchDayField::factory()->create(['match_day_id' => $matchDay]);
    $scheduled = CompetitionMatch::factory()
        ->scheduled($matchDay, $field, '09:00')
        ->create(['competition_id' => $this->competition]);

    $participant = User::factory()->participant()->create();
    $this->competition->participants()->attach($participant, ['availability_submitted_at' => now()]);
    MatchDayAvailability::factory()->create(['match_day_id' => $matchDay, 'user_id' => $participant]);

    $this->travel(1)->minutes();

    $this->put(route('competitions.match-days.update', [$this->competition, $matchDay]), [
        'date' => '2026-10-02',
        'starts_at' => '08:30',
        'ends_at' => '18:00',
    ])->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Match day updated.')]);

    expect(MatchDayAvailability::query()->where('match_day_id', $matchDay->id)->count())->toBe(1)
        ->and($scheduled->fresh()->match_day_id)->toBe($matchDay->id)
        ->and($scheduled->fresh()->starts_at)->toBe('09:00')
        ->and($participant->hasSubmittedAvailabilityFor($this->competition))->toBeTrue();
});

test('moving a match day to another date drops its availability and asks everyone for it again', function () {
    $admin = auth()->user();
    $movedDay = MatchDay::factory()->create(['competition_id' => $this->competition, 'date' => '2026-10-02']);
    $otherDay = MatchDay::factory()->create(['competition_id' => $this->competition, 'date' => '2026-10-03']);
    $field = MatchDayField::factory()->create(['match_day_id' => $movedDay]);
    CompetitionMatch::factory()->scheduled($movedDay, $field, '09:00')->create(['competition_id' => $this->competition]);

    $participant = User::factory()->participant()->create();
    $submittedAt = now()->startOfSecond();

    $this->competition->participants()->attach($admin, ['availability_submitted_at' => $submittedAt]);
    $this->competition->participants()->attach($participant, ['availability_submitted_at' => $submittedAt]);
    MatchDayAvailability::factory()->create(['match_day_id' => $movedDay, 'user_id' => $participant]);
    MatchDayAvailability::factory()->create(['match_day_id' => $otherDay, 'user_id' => $participant]);

    $this->travel(1)->minutes();

    $this->put(route('competitions.match-days.update', [$this->competition, $movedDay]), [
        'date' => '2026-10-05',
        'starts_at' => '09:00',
        'ends_at' => '17:00',
    ])->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => __('Match day moved. :count participant still has to submit their availability.', ['count' => 1])
            .' '.__(':count scheduled match was taken off the schedule.', ['count' => 1]),
    ]);

    // Het inzendmoment blijft staan; het nieuwe verzoek op de verzette dag
    // maakt de inzending niet-actueel.
    expect(MatchDayAvailability::query()->pluck('match_day_id')->all())->toBe([$otherDay->id])
        ->and(DB::table('competition_user')->pluck('availability_submitted_at')->unique()->all())->toBe([$submittedAt->toDateTimeString()])
        ->and($participant->hasSubmittedAvailabilityFor($this->competition))->toBeFalse();
});

test('moving a match day takes its pending matches off the schedule and keeps the played ones', function () {
    $matchDay = MatchDay::factory()->create(['competition_id' => $this->competition, 'date' => '2026-10-02']);
    $field = MatchDayField::factory()->create(['match_day_id' => $matchDay]);

    $scheduled = CompetitionMatch::factory()->scheduled($matchDay, $field, '09:00')->create(['competition_id' => $this->competition]);
    $pinned = CompetitionMatch::factory()->scheduled($matchDay, $field, '09:25')->pinned()->create(['competition_id' => $this->competition]);
    $played = CompetitionMatch::factory()->scheduled($matchDay, $field, '09:50')->played()->create(['competition_id' => $this->competition]);

    $this->put(route('competitions.match-days.update', [$this->competition, $matchDay]), [
        'date' => '2026-10-05',
        'starts_at' => '09:00',
        'ends_at' => '17:00',
    ])->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => __('Match day moved.').' '.__(':count scheduled matches were taken off the schedule.', ['count' => 2]),
    ]);

    foreach ([$scheduled, $pinned] as $match) {
        $fresh = $match->fresh();

        expect($fresh->match_day_id)->toBeNull()
            ->and($fresh->match_day_field_id)->toBeNull()
            ->and($fresh->starts_at)->toBeNull()
            ->and($fresh->ends_at)->toBeNull()
            ->and($fresh->pinned_at)->toBeNull()
            ->and($fresh->scheduling_failure)->toBeNull();
    }

    $fresh = $played->fresh();

    expect($fresh->match_day_id)->toBe($matchDay->id)
        ->and($fresh->match_day_field_id)->toBe($field->id)
        ->and($fresh->starts_at)->toBe('09:50')
        ->and($fresh->ends_at)->toBe('10:10');
});

test('moving a match day drops its availability regardless of the competition status', function (CompetitionStatus $status) {
    $this->competition->update(['status' => $status]);
    $matchDay = MatchDay::factory()->create(['competition_id' => $this->competition, 'date' => '2026-10-02']);

    $participant = User::factory()->participant()->create();
    $this->competition->participants()->attach($participant, ['availability_submitted_at' => now()]);
    MatchDayAvailability::factory()->create(['match_day_id' => $matchDay, 'user_id' => $participant]);

    $this->travel(1)->minutes();

    $this->put(route('competitions.match-days.update', [$this->competition, $matchDay]), [
        'date' => '2026-10-05',
        'starts_at' => '09:00',
        'ends_at' => '17:00',
    ])->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => __('Match day moved. Participants submit their availability once the competition is active.'),
    ]);

    expect(MatchDayAvailability::query()->count())->toBe(0)
        ->and($participant->hasSubmittedAvailabilityFor($this->competition))->toBeFalse();
})->with([CompetitionStatus::Draft, CompetitionStatus::Finished]);

test('the edit page shows what moving the match day would drop', function () {
    $matchDay = MatchDay::factory()->create(['competition_id' => $this->competition, 'date' => '2026-10-02']);
    $otherDay = MatchDay::factory()->create(['competition_id' => $this->competition, 'date' => '2026-10-03']);
    $field = MatchDayField::factory()->create(['match_day_id' => $matchDay]);

    MatchDayAvailability::factory()->count(2)->create(['match_day_id' => $matchDay]);
    MatchDayAvailability::factory()->create(['match_day_id' => $otherDay]);

    CompetitionMatch::factory()->scheduled($matchDay, $field, '09:00')->create(['competition_id' => $this->competition]);
    CompetitionMatch::factory()->scheduled($matchDay, $field, '09:25')->pinned()->create(['competition_id' => $this->competition]);
    CompetitionMatch::factory()->scheduled($matchDay, $field, '09:50')->played()->create(['competition_id' => $this->competition]);

    $this->get(route('competitions.match-days.edit', [$this->competition, $matchDay]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('moveImpact.available_count', 2)
            ->where('moveImpact.scheduled_match_count', 2)
            ->where('moveImpact.pinned_match_count', 1),
        );
});

test('a date with a time is rejected so it cannot count as a moved match day', function () {
    $matchDay = MatchDay::factory()->create(['competition_id' => $this->competition, 'date' => '2026-10-02']);
    MatchDayAvailability::factory()->create(['match_day_id' => $matchDay]);

    $this->put(route('competitions.match-days.update', [$this->competition, $matchDay]), [
        'date' => '2026-10-02 13:00',
        'starts_at' => '09:00',
        'ends_at' => '17:00',
    ])->assertSessionHasErrors('date');

    expect($matchDay->fresh()->date->toDateString())->toBe('2026-10-02')
        ->and(MatchDayAvailability::query()->count())->toBe(1);
});

test('a match day of another competition is not reachable', function () {
    $other = Competition::factory()->create();
    $matchDay = MatchDay::factory()->create(['competition_id' => $this->competition]);

    $this->get(route('competitions.match-days.edit', [$other, $matchDay]))->assertNotFound();
});

test('admins can delete a match day including its fields', function () {
    $matchDay = MatchDay::factory()
        ->withFields(2)
        ->create(['competition_id' => $this->competition]);

    $this->delete(route('competitions.match-days.destroy', [$this->competition, $matchDay]))
        ->assertRedirect(route('competitions.edit', $this->competition))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Match day removed.')]);

    expect(MatchDay::query()->count())->toBe(0)
        ->and(DB::table('match_day_fields')->count())->toBe(0);
});

test('the competition edit page lists the match days with their field count', function () {
    MatchDay::factory()->withFields(4)->create([
        'competition_id' => $this->competition,
        'date' => '2026-10-02',
    ]);
    MatchDay::factory()->create([
        'competition_id' => $this->competition,
        'date' => '2026-10-03',
    ]);

    $this->get(route('competitions.edit', $this->competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('competitions/edit')
            ->has('matchDays', 2)
            ->where('matchDays.0.date', '2026-10-02')
            ->where('matchDays.0.fields_count', 4)
            ->where('matchDays.1.fields_count', 0),
        );
});

test('participants cannot manage match days', function () {
    $matchDay = MatchDay::factory()->create(['competition_id' => $this->competition]);

    $this->actingAs(User::factory()->participant()->create());

    $this->get(route('competitions.match-days.edit', [$this->competition, $matchDay]))
        ->assertForbidden();

    $this->post(route('competitions.match-days.store', $this->competition), [])
        ->assertForbidden();
});

test('guests are redirected to the login page', function () {
    auth()->logout();

    $this->get(route('competitions.match-days.edit', [$this->competition, MatchDay::factory()->create(['competition_id' => $this->competition])]))
        ->assertRedirect(route('login'));
});

test('adding a match day asks every participant of the competition for their availability again', function () {
    $existingDay = MatchDay::factory()->create([
        'competition_id' => $this->competition,
        'date' => '2026-10-01',
    ]);
    $otherCompetition = Competition::factory()->create();

    $first = User::factory()->participant()->create();
    $second = User::factory()->participant()->create();
    $outsider = User::factory()->participant()->create();

    $submittedAt = now()->startOfSecond();

    $this->competition->participants()->attach($first, ['availability_submitted_at' => $submittedAt]);
    $this->competition->participants()->attach($second, ['availability_submitted_at' => $submittedAt]);
    $otherCompetition->participants()->attach($outsider, ['availability_submitted_at' => $submittedAt]);

    MatchDayAvailability::factory()->create(['match_day_id' => $existingDay, 'user_id' => $first]);
    MatchDayAvailability::factory()->create(['match_day_id' => $existingDay, 'user_id' => $second]);

    $this->travel(1)->minutes();

    $this->post(route('competitions.match-days.store', $this->competition), [
        'date' => '2026-10-02',
        'starts_at' => '09:00',
        'ends_at' => '17:00',
        'field_count' => 2,
    ])->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => __('Match day added. :count participants still have to submit their availability.', ['count' => 2]),
    ]);

    // Er wordt niets geleegd: het inzendmoment blijft staan en is nu ouder
    // dan de nieuwste speeldag.
    expect(DB::table('competition_user')->pluck('availability_submitted_at')->unique()->all())->toBe([$submittedAt->toDateTimeString()])
        ->and($first->hasSubmittedAvailabilityFor($this->competition))->toBeFalse()
        ->and($second->hasSubmittedAvailabilityFor($this->competition))->toBeFalse()
        ->and($outsider->hasSubmittedAvailabilityFor($otherCompetition))->toBeTrue()
        ->and(MatchDayAvailability::query()->count())->toBe(2);
});

test('adding a match day asks for the availability again regardless of the competition status', function (CompetitionStatus $status) {
    $this->competition->update(['status' => $status]);
    $participant = User::factory()->participant()->create();
    $this->competition->participants()->attach($participant, ['availability_submitted_at' => now()]);

    $this->travel(1)->minutes();

    $this->post(route('competitions.match-days.store', $this->competition), [
        'date' => '2026-10-02',
        'starts_at' => '09:00',
        'ends_at' => '17:00',
        'field_count' => 1,
    ])->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => __('Match day added. Participants submit their availability once the competition is active.'),
    ]);

    expect($participant->hasSubmittedAvailabilityFor($this->competition))->toBeFalse();
})->with([CompetitionStatus::Draft, CompetitionStatus::Finished]);

test('adding a match day without any participants keeps the plain message', function () {
    $this->post(route('competitions.match-days.store', $this->competition), [
        'date' => '2026-10-02',
        'starts_at' => '09:00',
        'ends_at' => '17:00',
        'field_count' => 1,
    ])->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Match day added.')]);
});

test('adding a match day counts a participant who never submitted', function () {
    $this->competition->participants()->attach(User::factory()->participant()->create());

    $this->post(route('competitions.match-days.store', $this->competition), [
        'date' => '2026-10-02',
        'starts_at' => '09:00',
        'ends_at' => '17:00',
        'field_count' => 1,
    ])->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => __('Match day added. :count participant still has to submit their availability.', ['count' => 1]),
    ]);
});

test('a second match day still reports the participants who have to submit', function () {
    $this->competition->participants()->attach(User::factory()->participant()->create(), ['availability_submitted_at' => now()]);
    $this->competition->participants()->attach(User::factory()->participant()->create(), ['availability_submitted_at' => now()]);

    $this->travel(1)->minutes();

    $payload = ['starts_at' => '09:00', 'ends_at' => '17:00', 'field_count' => 1];

    $this->post(route('competitions.match-days.store', $this->competition), [...$payload, 'date' => '2026-10-02']);

    $this->post(route('competitions.match-days.store', $this->competition), [...$payload, 'date' => '2026-10-03'])
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __('Match day added. :count participants still have to submit their availability.', ['count' => 2]),
        ]);
});

test('the admin who adds the match day is not counted in the message', function () {
    $admin = auth()->user();
    $other = User::factory()->participant()->create();

    $this->competition->participants()->attach($admin, ['availability_submitted_at' => now()]);
    $this->competition->participants()->attach($other, ['availability_submitted_at' => now()]);

    $this->travel(1)->minutes();

    $this->post(route('competitions.match-days.store', $this->competition), [
        'date' => '2026-10-02',
        'starts_at' => '09:00',
        'ends_at' => '17:00',
        'field_count' => 1,
    ])->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => __('Match day added. :count participant still has to submit their availability.', ['count' => 1]),
    ]);
});
