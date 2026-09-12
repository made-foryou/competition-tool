<?php

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayField;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());

    $this->competition = Competition::factory()->create();
    $this->matchDay = MatchDay::factory()
        ->withFields(2)
        ->create(['competition_id' => $this->competition]);
});

test('admins can add a field which gets the next position', function () {
    $this->post(route('competitions.match-days.fields.store', [$this->competition, $this->matchDay]), [
        'name' => 'Baan A',
    ])->assertRedirect();

    $field = $this->matchDay->fields()->where('name', 'Baan A')->firstOrFail();

    expect($field->position)->toBe(3)
        ->and($this->matchDay->fields()->count())->toBe(3);
});

test('removing a field does not renumber the others but keeps the order', function () {
    $first = $this->matchDay->fields()->where('position', 1)->firstOrFail();

    $this->delete(route('competitions.match-days.fields.destroy', [$this->competition, $this->matchDay, $first]))
        ->assertRedirect();

    $this->post(route('competitions.match-days.fields.store', [$this->competition, $this->matchDay]), [
        'name' => 'Baan A',
    ]);

    expect($this->matchDay->fields()->pluck('position')->all())->toBe([2, 3])
        ->and($this->matchDay->fields()->pluck('name')->all())->toBe(['Veld 2', 'Baan A']);
});

test('a duplicate field name within the same match day is rejected', function () {
    $this->post(route('competitions.match-days.fields.store', [$this->competition, $this->matchDay]), [
        'name' => 'Veld 1',
    ])->assertSessionHasErrors('name');

    expect($this->matchDay->fields()->count())->toBe(2);
});

test('the same field name is allowed on another match day', function () {
    $other = MatchDay::factory()->create(['competition_id' => $this->competition]);

    $this->post(route('competitions.match-days.fields.store', [$this->competition, $other]), [
        'name' => 'Veld 1',
    ])->assertRedirect();

    expect($other->fields()->count())->toBe(1);
});

test('admins can remove a field', function () {
    $field = $this->matchDay->fields()->firstOrFail();

    $this->delete(route('competitions.match-days.fields.destroy', [$this->competition, $this->matchDay, $field]))
        ->assertRedirect();

    expect(MatchDayField::query()->whereKey($field->id)->exists())->toBeFalse();
});

test('a field of another match day cannot be removed', function () {
    $other = MatchDay::factory()->withFields(1)->create(['competition_id' => $this->competition]);
    $field = $other->fields()->firstOrFail();

    $this->delete(route('competitions.match-days.fields.destroy', [$this->competition, $this->matchDay, $field]))
        ->assertNotFound();

    expect(MatchDayField::query()->whereKey($field->id)->exists())->toBeTrue();
});

test('participants cannot manage fields', function () {
    $this->actingAs(User::factory()->participant()->create());

    $this->post(route('competitions.match-days.fields.store', [$this->competition, $this->matchDay]), [
        'name' => 'Baan A',
    ])->assertForbidden();
});

test('guests are redirected to the login page', function () {
    auth()->logout();

    $this->post(route('competitions.match-days.fields.store', [$this->competition, $this->matchDay]), [
        'name' => 'Baan A',
    ])->assertRedirect(route('login'));
});
