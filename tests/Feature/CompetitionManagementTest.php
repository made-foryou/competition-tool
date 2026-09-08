<?php

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function actingAsAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    test()->actingAs($admin);

    return $admin;
}

test('admins can view the competitions index', function () {
    actingAsAdmin();
    Competition::factory()->count(2)->create();

    $this->get(route('competitions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('competitions/index')
            ->has('competitions', 2),
        );
});

test('participants cannot access competition management', function () {
    $this->actingAs(User::factory()->participant()->create())
        ->get(route('competitions.index'))
        ->assertForbidden();
});

test('guests are redirected to the login page', function () {
    $this->get(route('competitions.index'))->assertRedirect(route('login'));
});

test('guests visiting the edit page are redirected to the login page', function () {
    $competition = Competition::factory()->create();

    $this->get(route('competitions.edit', $competition))->assertRedirect(route('login'));
});

test('admins can create a competition with an auto-generated slug', function () {
    actingAsAdmin();

    $this->post(route('competitions.store'), [
        'name' => 'Voorjaarstoernooi 2026',
        'description' => 'Het jaarlijkse toernooi.',
        'location' => 'Sporthal Noord',
        'starts_at' => '2026-10-01',
        'ends_at' => '2026-10-02',
        'status' => CompetitionStatus::Draft->value,
    ])->assertRedirect();

    $competition = Competition::query()->firstOrFail();
    expect($competition->slug)->toBe('voorjaarstoernooi-2026')
        ->and($competition->status)->toBe(CompetitionStatus::Draft);
});

test('a competition name resulting in a reserved slug is rejected', function () {
    actingAsAdmin();

    $this->post(route('competitions.store'), [
        'name' => 'Dashboard',
        'starts_at' => '2026-10-01',
        'status' => CompetitionStatus::Draft->value,
    ])->assertSessionHasErrors('slug');
});

test('a duplicate slug is rejected', function () {
    actingAsAdmin();
    Competition::factory()->create(['slug' => 'voorjaarstoernooi-2026']);

    $this->post(route('competitions.store'), [
        'name' => 'Voorjaarstoernooi 2026',
        'starts_at' => '2026-10-01',
        'status' => CompetitionStatus::Draft->value,
    ])->assertSessionHasErrors('slug');
});

test('admins can update a competition and keep its own slug', function () {
    actingAsAdmin();
    $competition = Competition::factory()->create(['name' => 'Oud', 'slug' => 'oud']);

    $this->put(route('competitions.update', $competition), [
        'name' => 'Oud',
        'description' => 'Bijgewerkt.',
        'location' => null,
        'starts_at' => '2026-11-01',
        'ends_at' => null,
        'status' => CompetitionStatus::Active->value,
    ])->assertRedirect(route('competitions.edit', $competition));

    expect($competition->refresh()->description)->toBe('Bijgewerkt.')
        ->and($competition->status)->toBe(CompetitionStatus::Active);
});

test('the end date may not be before the start date', function () {
    actingAsAdmin();

    $this->post(route('competitions.store'), [
        'name' => 'Toernooi',
        'starts_at' => '2026-10-02',
        'ends_at' => '2026-10-01',
        'status' => CompetitionStatus::Draft->value,
    ])->assertSessionHasErrors('ends_at');
});

test('admins can delete a competition', function () {
    actingAsAdmin();
    $competition = Competition::factory()->create();

    $this->delete(route('competitions.destroy', $competition))
        ->assertRedirect(route('competitions.index'));

    expect(Competition::query()->count())->toBe(0);
});
