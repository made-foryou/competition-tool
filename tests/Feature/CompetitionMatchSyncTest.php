<?php

use App\Actions\Competitions\SyncCompetitionMatches;
use App\Enums\MatchStatus;
use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Controleert dat de wedstrijdenlijst een geldige enkelvoudige round-robin
 * is: elk paar canoniek (laagste id eerst), elk paar uniek en niemand tegen
 * zichzelf.
 */
function expectValidRoundRobin(Competition $competition): void
{
    $matches = $competition->matches()->get();

    $pairs = [];

    foreach ($matches as $match) {
        expect($match->first_player_id)->toBeLessThan($match->second_player_id);

        $pairs[] = $match->first_player_id.'-'.$match->second_player_id;
    }

    expect($pairs)->toBe(array_unique($pairs));
}

test('linking an existing account adds matches against every participant', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());
    $competition = Competition::factory()->create();
    [$first, $second] = User::factory()->participant()->count(2)->create()->all();
    $competition->participants()->attach([$first->id, $second->id]);
    $third = User::factory()->participant()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => $third->email,
    ])->assertRedirect();

    expect($competition->matches()->count())->toBe(3)
        ->and($competition->matches()->where('status', MatchStatus::Pending)->count())->toBe(3);
    expectValidRoundRobin($competition);
});

test('creating a new account directly adds matches for the new participant', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());
    $competition = Competition::factory()->create();
    $existing = User::factory()->participant()->create();
    $competition->participants()->attach($existing);

    $this->post(route('competitions.participants.store', $competition), [
        'email' => 'direct@example.com',
        'mode' => 'create',
        'name' => 'Directe Deelnemer',
        'password' => 'SuperSecret123!',
    ])->assertRedirect();

    $created = User::query()->where('email', 'direct@example.com')->firstOrFail();

    expect($competition->matches()->count())->toBe(1)
        ->and($competition->matches()->first()->second_player_id)->toBe($created->id);
    expectValidRoundRobin($competition);
});

test('accepting an invitation adds matches against all existing participants', function () {
    $competition = Competition::factory()->create();
    $participants = User::factory()->participant()->count(2)->create();
    $competition->participants()->attach($participants);

    $plainToken = Str::random(64);
    Invitation::factory()->create([
        'token' => hash('sha256', $plainToken),
        'competition_id' => $competition->id,
        'role' => UserRole::Participant,
    ]);

    $this->post(route('invitation.store', $plainToken), [
        'name' => 'Nieuwe Deelnemer',
        'password' => 'nieuw-wachtwoord',
        'password_confirmation' => 'nieuw-wachtwoord',
    ])->assertRedirect(route('competition.dashboard', $competition));

    $accepted = User::query()->where('name', 'Nieuwe Deelnemer')->firstOrFail();

    expect($competition->matches()->count())->toBe(3)
        ->and($competition->matches()
            ->where(fn ($query) => $query
                ->where('first_player_id', $accepted->id)
                ->orWhere('second_player_id', $accepted->id))
            ->count())->toBe(2);
    expectValidRoundRobin($competition);
});

test('self registration adds matches for the new participant', function () {
    $competition = Competition::factory()->create();
    $existing = User::factory()->participant()->create();
    $competition->participants()->attach($existing);

    $this->post(route('competition.register.store', $competition), [
        'name' => 'Sanne de Vries',
        'nickname' => '',
        'email' => 'sanne@example.com',
        'password' => 'wachtwoord-van-sanne',
        'password_confirmation' => 'wachtwoord-van-sanne',
        'match_days' => [],
    ])->assertRedirect(route('competition.dashboard', $competition));

    expect($competition->matches()->count())->toBe(1);
    expectValidRoundRobin($competition);
});

test('removing a participant deletes only their pending matches', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());
    $competition = Competition::factory()->create();
    $participants = User::factory()->participant()->count(4)->create();
    $competition->participants()->attach($participants);
    app(SyncCompetitionMatches::class)->handle($competition);

    $removed = $participants->first();
    $untouchedMatchIds = $competition->matches()
        ->where('first_player_id', '!=', $removed->id)
        ->where('second_player_id', '!=', $removed->id)
        ->pluck('id')
        ->all();

    $this->delete(route('competitions.participants.destroy', [$competition, $removed]))
        ->assertRedirect();

    expect($competition->matches()->count())->toBe(3)
        ->and($competition->matches()->pluck('id')->all())->toBe($untouchedMatchIds)
        ->and($competition->matches()
            ->where(fn ($query) => $query
                ->where('first_player_id', $removed->id)
                ->orWhere('second_player_id', $removed->id))
            ->exists())->toBeFalse();
});

test('removing a participant keeps their played matches', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());
    $competition = Competition::factory()->create();
    [$first, $second, $third] = User::factory()->participant()->count(3)->create()->all();
    $competition->participants()->attach([$first->id, $second->id, $third->id]);

    $playedMatch = CompetitionMatch::factory()->played()->create([
        'competition_id' => $competition->id,
        'first_player_id' => min($first->id, $second->id),
        'second_player_id' => max($first->id, $second->id),
    ]);
    app(SyncCompetitionMatches::class)->handle($competition);

    $this->delete(route('competitions.participants.destroy', [$competition, $second]))
        ->assertRedirect();

    $this->assertModelExists($playedMatch);
    expect($competition->matches()->count())->toBe(2)
        ->and($competition->matches()->where('status', MatchStatus::Pending)->count())->toBe(1)
        ->and($competition->matches()
            ->where('status', MatchStatus::Pending)
            ->where(fn ($query) => $query
                ->where('first_player_id', $second->id)
                ->orWhere('second_player_id', $second->id))
            ->exists())->toBeFalse();
});

test('re-adding a participant with a played match does not duplicate that pair', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());
    $competition = Competition::factory()->create();
    [$first, $second, $third] = User::factory()->participant()->count(3)->create()->all();
    $competition->participants()->attach([$first->id, $second->id, $third->id]);

    CompetitionMatch::factory()->played()->create([
        'competition_id' => $competition->id,
        'first_player_id' => min($first->id, $second->id),
        'second_player_id' => max($first->id, $second->id),
    ]);
    app(SyncCompetitionMatches::class)->handle($competition);

    $this->delete(route('competitions.participants.destroy', [$competition, $second]));

    $this->post(route('competitions.participants.store', $competition), [
        'email' => $second->email,
    ])->assertRedirect();

    expect($competition->matches()->count())->toBe(3)
        ->and($competition->matches()->where('status', MatchStatus::Played)->count())->toBe(1)
        ->and($competition->matches()->where('status', MatchStatus::Pending)->count())->toBe(2);
    expectValidRoundRobin($competition);
});

test('a competition with fewer than two participants gets no matches', function (int $participantCount) {
    $competition = Competition::factory()->create();
    $competition->participants()->attach(
        User::factory()->participant()->count($participantCount)->create(),
    );

    app(SyncCompetitionMatches::class)->handle($competition);

    expect($competition->matches()->count())->toBe(0);
})->with(['no participants' => 0, 'one participant' => 1]);

test('adding five participants one by one yields exactly ten matches', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());
    $competition = Competition::factory()->create();

    foreach (User::factory()->participant()->count(5)->create() as $participant) {
        $this->post(route('competitions.participants.store', $competition), [
            'email' => $participant->email,
        ])->assertRedirect();
    }

    expect($competition->matches()->count())->toBe(10)
        ->and($competition->matches()->where('status', MatchStatus::Pending)->count())->toBe(10);
    expectValidRoundRobin($competition);
});
