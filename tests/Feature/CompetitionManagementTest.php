<?php

use App\Actions\Competitions\SyncCompetitionMatches;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
            ->has('competitions.data', 2)
            ->where('competitions.total', 2)
            ->where('filters.search', null)
            ->where('filters.status', null)
            ->etc(),
        );
});

test('the index can be searched by name', function () {
    actingAsAdmin();
    Competition::factory()->create(['name' => 'Voorjaarstoernooi', 'slug' => 'voorjaarstoernooi']);
    Competition::factory()->create(['name' => 'Najaarstoernooi', 'slug' => 'najaarstoernooi']);

    $this->get(route('competitions.index', ['search' => 'voorjaar']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('competitions.data', 1)
            ->where('competitions.data.0.name', 'Voorjaarstoernooi')
            ->where('filters.search', 'voorjaar')
            ->etc(),
        );
});

test('a search term with a like wildcard does not match everything', function () {
    actingAsAdmin();
    Competition::factory()->create(['name' => 'Voorjaarstoernooi', 'slug' => 'voorjaarstoernooi']);
    Competition::factory()->create(['name' => 'Najaarstoernooi', 'slug' => 'najaarstoernooi']);

    $this->get(route('competitions.index', ['search' => '%']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('competitions.data', 0)
            ->etc(),
        );
});

test('a search term longer than one hundred characters is rejected', function () {
    actingAsAdmin();

    $this->get(route('competitions.index', ['search' => str_repeat('a', 101)]))
        ->assertSessionHasErrors('search');
});

test('pagination links preserve the active filters', function () {
    actingAsAdmin();
    Competition::factory()->count(16)->create(['name' => 'Voorjaarstoernooi']);

    $this->get(route('competitions.index', ['search' => 'Voorjaar']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('competitions.links.2.url', fn (?string $url) => $url !== null && str_contains($url, 'search='))
            ->etc(),
        );
});

test('the index can be filtered by status', function () {
    actingAsAdmin();
    Competition::factory()->create(['name' => 'Actief', 'slug' => 'actief']);
    Competition::factory()->draft()->create(['name' => 'Concept', 'slug' => 'concept']);

    $this->get(route('competitions.index', ['status' => CompetitionStatus::Draft->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('competitions.data', 1)
            ->where('competitions.data.0.name', 'Concept')
            ->where('filters.status', CompetitionStatus::Draft->value)
            ->etc(),
        );
});

test('the index paginates at fifteen competitions per page', function () {
    actingAsAdmin();
    Competition::factory()->count(16)->create();

    $this->get(route('competitions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('competitions.data', 15)
            ->where('competitions.last_page', 2)
            ->where('competitions.total', 16)
            ->etc(),
        );

    $this->get(route('competitions.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('competitions.data', 1)
            ->etc(),
        );
});

test('an unknown status filter is rejected', function () {
    actingAsAdmin();

    $this->get(route('competitions.index', ['status' => 'archived']))
        ->assertSessionHasErrors('status');
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
        'type' => CompetitionType::TheoSchilthuizenBokaal->value,
    ])->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Competition created.')]);

    $competition = Competition::query()->firstOrFail();
    expect($competition->slug)->toBe('voorjaarstoernooi-2026')
        ->and($competition->status)->toBe(CompetitionStatus::Draft)
        ->and($competition->type)->toBe(CompetitionType::TheoSchilthuizenBokaal);
});

test('a competition name resulting in a reserved slug is rejected', function () {
    actingAsAdmin();

    $this->post(route('competitions.store'), [
        'name' => 'Dashboard',
        'starts_at' => '2026-10-01',
        'status' => CompetitionStatus::Draft->value,
        'type' => CompetitionType::TheoSchilthuizenBokaal->value,
    ])->assertSessionHasErrors('slug');
});

test('a duplicate slug is rejected', function () {
    actingAsAdmin();
    Competition::factory()->create(['slug' => 'voorjaarstoernooi-2026']);

    $this->post(route('competitions.store'), [
        'name' => 'Voorjaarstoernooi 2026',
        'starts_at' => '2026-10-01',
        'status' => CompetitionStatus::Draft->value,
        'type' => CompetitionType::TheoSchilthuizenBokaal->value,
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
        'type' => CompetitionType::TheoSchilthuizenBokaal->value,
    ])->assertRedirect(route('competitions.edit', $competition))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Competition updated.')]);

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
        'type' => CompetitionType::TheoSchilthuizenBokaal->value,
    ])->assertSessionHasErrors('ends_at');
});

test('an existing competition without an explicit type defaults to the Theo Schilthuizen bokaal', function () {
    actingAsAdmin();
    $attributes = Arr::except(Competition::factory()->raw(), ['type']);
    $id = DB::table('competitions')->insertGetId($attributes);

    expect(Competition::query()->findOrFail($id)->type)->toBe(CompetitionType::TheoSchilthuizenBokaal);
});

test('admins can store a competition with an explicit type', function () {
    actingAsAdmin();

    $this->post(route('competitions.store'), [
        'name' => 'Zomertoernooi 2026',
        'starts_at' => '2026-10-01',
        'status' => CompetitionStatus::Draft->value,
        'type' => CompetitionType::TheoSchilthuizenBokaal->value,
    ])->assertRedirect();

    expect(Competition::query()->firstOrFail()->type)->toBe(CompetitionType::TheoSchilthuizenBokaal);
});

test('an invalid type is rejected on store', function () {
    actingAsAdmin();

    $this->post(route('competitions.store'), [
        'name' => 'Zomertoernooi 2026',
        'starts_at' => '2026-10-01',
        'status' => CompetitionStatus::Draft->value,
        'type' => 'foo',
    ])->assertSessionHasErrors('type');
});

test('an invalid type is rejected on update', function () {
    actingAsAdmin();
    $competition = Competition::factory()->create();

    $this->put(route('competitions.update', $competition), [
        'name' => $competition->name,
        'starts_at' => $competition->starts_at->toDateString(),
        'status' => CompetitionStatus::Draft->value,
        'type' => 'foo',
    ])->assertSessionHasErrors('type');
});

test('admins can delete a competition', function () {
    actingAsAdmin();
    $competition = Competition::factory()->create();

    $this->delete(route('competitions.destroy', $competition))
        ->assertRedirect(route('competitions.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Competition deleted.')]);

    expect(Competition::query()->count())->toBe(0);
});

test('the edit page shows the availability of each participant', function () {
    actingAsAdmin();

    $competition = Competition::factory()->create();
    $matchDay = MatchDay::factory()->create(['competition_id' => $competition]);

    $filledIn = User::factory()->participant()->create(['name' => 'Anna']);
    $competition->participants()->attach($filledIn, ['availability_submitted_at' => now()]);
    MatchDayAvailability::factory()->create([
        'match_day_id' => $matchDay,
        'user_id' => $filledIn,
    ]);

    $pending = User::factory()->participant()->create(['name' => 'Bob']);
    $competition->participants()->attach($pending);

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('availability', 2)
            ->where('availability.0.name', 'Anna')
            ->where('availability.0.submitted', true)
            ->where('availability.0.match_day_ids', [$matchDay->id])
            ->where('availability.1.name', 'Bob')
            ->where('availability.1.submitted', false)
            ->where('availability.1.match_day_ids', []),
        );
});

test('the edit page shows the competition type and its synced matches', function () {
    actingAsAdmin();

    $competition = Competition::factory()->create(['type' => CompetitionType::TheoSchilthuizenBokaal]);
    $participants = User::factory()->participant()->count(3)->create();
    $competition->participants()->attach($participants);
    app(SyncCompetitionMatches::class)->handle($competition);

    $matches = $competition->matches()->with(['firstPlayer', 'secondPlayer'])->get();

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('competition.type', CompetitionType::TheoSchilthuizenBokaal->value)
            ->has('matches', 3)
            ->where('matches.0.id', $matches[0]->id)
            ->where('matches.0.first_player', $matches[0]->firstPlayer->display_name)
            ->where('matches.0.second_player', $matches[0]->secondPlayer->display_name)
            ->where('matches.0.status', $matches[0]->status->value),
        );
});
