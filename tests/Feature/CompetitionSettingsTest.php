<?php

use App\Actions\Competitions\UpdateCompetitionSettings;
use App\Enums\MatchStatus;
use App\Enums\PointsSystem;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\User;
use App\Support\CompetitionSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function actingAsSettingsAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    test()->actingAs($admin);

    return $admin;
}

test('a new competition has default planning settings', function () {
    $competition = Competition::factory()->create();

    expect($competition->settings)->toBeInstanceOf(CompetitionSettings::class)
        ->and($competition->settings->matchDurationMinutes)->toBe(20)
        ->and($competition->settings->bufferMinutes)->toBe(5)
        ->and($competition->settings->minRestMinutes)->toBe(10)
        ->and($competition->settings->breakDurationMinutes)->toBe(15)
        ->and($competition->settings->usePools)->toBeFalse()
        ->and($competition->settings->poolSize)->toBeNull()
        ->and($competition->settings->maxMatchesPerPlayerPerDay)->toBe(0)
        ->and($competition->settings->bestOf)->toBe(5)
        ->and($competition->settings->pointsSystem)->toBe(PointsSystem::WinTwoLossZero)
        ->and($competition->settings->gamesToWin())->toBe(3);
});

test('the number of games to win follows from the match format', function (int $bestOf, int $gamesToWin) {
    $settings = CompetitionSettings::fromArray(['best_of' => $bestOf]);

    expect($settings->gamesToWin())->toBe($gamesToWin);
})->with([
    'best of 1' => [1, 1],
    'best of 3' => [3, 2],
    'best of 5' => [5, 3],
    'best of 7' => [7, 4],
]);

test('a result is valid when exactly one player has the games to win', function (int $bestOf, int $firstPlayerScore, int $secondPlayerScore, bool $isValid) {
    $settings = CompetitionSettings::fromArray(['best_of' => $bestOf]);

    expect($settings->isValidResult($firstPlayerScore, $secondPlayerScore))->toBe($isValid);
})->with([
    'best of 5, 3-0' => [5, 3, 0, true],
    'best of 5, 2-3' => [5, 2, 3, true],
    'best of 5, a draw at 3' => [5, 3, 3, false],
    'best of 5, no winner' => [5, 2, 1, false],
    'best of 5, above the maximum' => [5, 4, 1, false],
    'best of 5, negative' => [5, 3, -1, false],
    'best of 3, 2-1' => [3, 2, 1, true],
    'best of 3, three games' => [3, 3, 1, false],
    'best of 1, 0-1' => [1, 0, 1, true],
    'best of 1, a draw' => [1, 1, 1, false],
    'best of 1, no winner' => [1, 0, 0, false],
    'best of 7, 4-3' => [7, 4, 3, true],
]);

test('the withSettings factory state applies the given settings', function () {
    $settings = new CompetitionSettings(
        matchDurationMinutes: 30,
        bufferMinutes: 8,
        minRestMinutes: 12,
        breakDurationMinutes: 25,
        usePools: true,
        poolSize: 6,
        maxMatchesPerPlayerPerDay: 4,
        bestOf: 7,
        pointsSystem: PointsSystem::GamesWon,
    );

    $competition = Competition::factory()->withSettings($settings)->create();

    expect($competition->refresh()->settings)
        ->matchDurationMinutes->toBe(30)
        ->bufferMinutes->toBe(8)
        ->minRestMinutes->toBe(12)
        ->breakDurationMinutes->toBe(25)
        ->usePools->toBeTrue()
        ->poolSize->toBe(6)
        ->maxMatchesPerPlayerPerDay->toBe(4)
        ->bestOf->toBe(7)
        ->pointsSystem->toBe(PointsSystem::GamesWon);
});

test('the edit page exposes the settings and their limits', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();

    $this->get(route('competitions.edit', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('competitions/edit')
            ->has('settings')
            ->where('settings.match_duration_minutes', 20)
            ->where('settings.use_pools', false)
            ->where('settings.best_of', 5)
            ->where('settings.points_system', 'win-2-loss-0')
            ->where('settingsLimits.best_of.options', [1, 3, 5, 7])
            ->where('settingsLimits.points_system.options', ['win-2-loss-0', 'win-2-loss-1', 'games-won'])
            ->where('settingsLimits.points_system.default', 'win-2-loss-0')
            ->has('settingsLimits.pool_size.min')
            ->etc(),
        );
});

test('admins can update the planning settings', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();

    $this->put(route('competitions.settings.update', $competition), [
        'match_duration_minutes' => 25,
        'buffer_minutes' => 10,
        'min_rest_minutes' => 15,
        'break_duration_minutes' => 20,
        'use_pools' => '1',
        'pool_size' => 5,
        'max_matches_per_player_per_day' => 3,
        'best_of' => 5,
        'points_system' => 'win-2-loss-0',
    ])->assertRedirect(route('competitions.edit', $competition))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Planning settings saved.')]);

    $settings = $competition->refresh()->settings;

    expect($settings->matchDurationMinutes)->toBe(25)
        ->and($settings->bufferMinutes)->toBe(10)
        ->and($settings->minRestMinutes)->toBe(15)
        ->and($settings->breakDurationMinutes)->toBe(20)
        ->and($settings->usePools)->toBeTrue()
        ->and($settings->poolSize)->toBe(5)
        ->and($settings->maxMatchesPerPlayerPerDay)->toBe(3)
        ->and($settings->bestOf)->toBe(5)
        ->and($settings->pointsSystem)->toBe(PointsSystem::WinTwoLossZero);

    $stored = json_decode(DB::table('competitions')->where('id', $competition->id)->value('settings'), true);

    expect($stored)->toBeArray()
        ->and($stored)->toHaveKeys([
            'match_duration_minutes',
            'buffer_minutes',
            'min_rest_minutes',
            'break_duration_minutes',
            'use_pools',
            'pool_size',
            'max_matches_per_player_per_day',
            'best_of',
            'points_system',
        ]);
});

test('the pool size is discarded when pools are disabled', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();

    $this->put(route('competitions.settings.update', $competition), [
        'match_duration_minutes' => 20,
        'buffer_minutes' => 5,
        'min_rest_minutes' => 10,
        'break_duration_minutes' => 15,
        'use_pools' => '0',
        'pool_size' => 5,
        'max_matches_per_player_per_day' => 0,
        'best_of' => 5,
        'points_system' => 'win-2-loss-0',
    ])->assertRedirect(route('competitions.edit', $competition));

    expect($competition->refresh()->settings->poolSize)->toBeNull();
});

test('a pool size is required when pools are enabled', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();

    $this->put(route('competitions.settings.update', $competition), [
        'match_duration_minutes' => 20,
        'buffer_minutes' => 5,
        'min_rest_minutes' => 10,
        'break_duration_minutes' => 15,
        'use_pools' => '1',
        'max_matches_per_player_per_day' => 0,
        'best_of' => 5,
        'points_system' => 'win-2-loss-0',
    ])->assertSessionHasErrors('pool_size');
});

test('planning settings validation limits are enforced', function (string $field, mixed $value) {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();

    $payload = [
        'match_duration_minutes' => 20,
        'buffer_minutes' => 5,
        'min_rest_minutes' => 10,
        'break_duration_minutes' => 15,
        'use_pools' => '1',
        'pool_size' => 5,
        'max_matches_per_player_per_day' => 0,
        'best_of' => 5,
        'points_system' => 'win-2-loss-0',
        $field => $value,
    ];

    $this->put(route('competitions.settings.update', $competition), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'match duration too short' => ['match_duration_minutes', 4],
    'match duration too long' => ['match_duration_minutes', 121],
    'buffer too short' => ['buffer_minutes', -1],
    'buffer too long' => ['buffer_minutes', 61],
    'min rest too short' => ['min_rest_minutes', -1],
    'min rest too long' => ['min_rest_minutes', 61],
    'break duration too short' => ['break_duration_minutes', -1],
    'break duration too long' => ['break_duration_minutes', 121],
    'pool size too small' => ['pool_size', 2],
    'pool size too large' => ['pool_size', 9],
    'negative max matches' => ['max_matches_per_player_per_day', -1],
    'max matches too large' => ['max_matches_per_player_per_day', 51],
    'non-numeric match duration' => ['match_duration_minutes', 'abc'],
    'best of 2' => ['best_of', 2],
    'best of 4' => ['best_of', 4],
    'best of 9' => ['best_of', 9],
    'non-numeric best of' => ['best_of', 'abc'],
    'unknown points system' => ['points_system', 'win-3'],
]);

test('a missing required field is rejected', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();

    $this->put(route('competitions.settings.update', $competition), [
        'buffer_minutes' => 5,
        'min_rest_minutes' => 10,
        'break_duration_minutes' => 15,
        'use_pools' => '0',
        'max_matches_per_player_per_day' => 0,
        'best_of' => 5,
        'points_system' => 'win-2-loss-0',
    ])->assertSessionHasErrors('match_duration_minutes');
});

test('a missing use_pools field is rejected', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();

    $this->put(route('competitions.settings.update', $competition), [
        'match_duration_minutes' => 20,
        'buffer_minutes' => 5,
        'min_rest_minutes' => 10,
        'break_duration_minutes' => 15,
        'max_matches_per_player_per_day' => 0,
        'best_of' => 5,
        'points_system' => 'win-2-loss-0',
    ])->assertSessionHasErrors('use_pools');
});

test('use_pools accepts the "on" checkbox value', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();

    $this->put(route('competitions.settings.update', $competition), [
        'match_duration_minutes' => 20,
        'buffer_minutes' => 5,
        'min_rest_minutes' => 10,
        'break_duration_minutes' => 15,
        'use_pools' => 'on',
        'pool_size' => 4,
        'max_matches_per_player_per_day' => 0,
        'best_of' => 5,
        'points_system' => 'win-2-loss-0',
    ])->assertRedirect(route('competitions.edit', $competition));

    expect($competition->refresh()->settings->usePools)->toBeTrue();
});

test('unknown settings keys are ignored and missing keys fall back to their default', function () {
    $competition = Competition::factory()->create();

    DB::table('competitions')->where('id', $competition->id)->update([
        'settings' => json_encode([
            'match_duration_minutes' => 30,
            'future_key' => 'something-not-yet-supported',
        ]),
    ]);

    $settings = $competition->refresh()->settings;

    expect($settings->matchDurationMinutes)->toBe(30)
        ->and($settings->bufferMinutes)->toBe(CompetitionSettings::DEFAULT_BUFFER_MINUTES)
        ->and($settings->bestOf)->toBe(CompetitionSettings::DEFAULT_BEST_OF)
        ->and($settings->pointsSystem)->toBe(CompetitionSettings::DEFAULT_POINTS_SYSTEM);
});

test('a missing best_of is rejected', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();

    $this->put(route('competitions.settings.update', $competition), [
        'match_duration_minutes' => 20,
        'buffer_minutes' => 5,
        'min_rest_minutes' => 10,
        'break_duration_minutes' => 15,
        'use_pools' => '0',
        'max_matches_per_player_per_day' => 0,
    ])->assertSessionHasErrors('best_of');
});

test('a missing points_system is rejected', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();

    $payload = lockedFormatPayload();
    unset($payload['points_system']);

    $this->put(route('competitions.settings.update', $competition), $payload)
        ->assertSessionHasErrors('points_system');
});

test('admins can save a different points system', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();

    $this->put(route('competitions.settings.update', $competition), lockedFormatPayload(['points_system' => 'games-won']))
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('competitions.edit', $competition));

    $stored = json_decode(DB::table('competitions')->where('id', $competition->id)->value('settings'), true);

    expect($stored['points_system'])->toBe('games-won')
        ->and($competition->refresh()->settings->pointsSystem)->toBe(PointsSystem::GamesWon);
});

test('an unknown points system is rejected with a message', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();

    $this->put(route('competitions.settings.update', $competition), lockedFormatPayload(['points_system' => 'win-3']))
        ->assertSessionHasErrors(['points_system' => __('Choose a valid points system.')]);

    expect($competition->refresh()->settings->pointsSystem)->toBe(PointsSystem::WinTwoLossZero);
});

test('a missing or unknown stored points system falls back to the default', function (array $stored) {
    $competition = Competition::factory()->create();

    DB::table('competitions')->where('id', $competition->id)->update(['settings' => json_encode($stored)]);

    expect($competition->refresh()->settings->pointsSystem)->toBe(PointsSystem::WinTwoLossZero);
})->with([
    'missing key' => [['best_of' => 3]],
    'unknown value' => [['points_system' => 'win-3']],
    'wrong type' => [['points_system' => 5]],
]);

test('the points system cannot change once a result has been entered', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();
    CompetitionMatch::factory()->played()->create(['competition_id' => $competition->id]);

    $this->put(route('competitions.settings.update', $competition), lockedFormatPayload(['points_system' => 'games-won']))
        ->assertSessionHasErrors(['points_system' => __('The points system cannot be changed once a result has been entered.')])
        ->assertSessionDoesntHaveErrors('best_of');

    expect($competition->refresh()->settings->pointsSystem)->toBe(PointsSystem::WinTwoLossZero);
});

test('the same points system can be sent again after a result', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();
    CompetitionMatch::factory()->played()->create(['competition_id' => $competition->id]);

    $this->put(route('competitions.settings.update', $competition), lockedFormatPayload())
        ->assertSessionDoesntHaveErrors();
});

test('a changed format and points system are both reported after a result', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();
    CompetitionMatch::factory()->played()->create(['competition_id' => $competition->id]);

    $this->put(route('competitions.settings.update', $competition), lockedFormatPayload(['best_of' => 3, 'points_system' => 'games-won']))
        ->assertSessionHasErrors(['best_of', 'points_system']);

    expect($competition->refresh()->settings)
        ->bestOf->toBe(5)
        ->pointsSystem->toBe(PointsSystem::WinTwoLossZero);
});

test('an invalid best of does not hide the locked points system error', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();
    CompetitionMatch::factory()->played()->create(['competition_id' => $competition->id]);

    $this->put(route('competitions.settings.update', $competition), lockedFormatPayload(['best_of' => 4, 'points_system' => 'games-won']))
        ->assertSessionHasErrors(['best_of', 'points_system' => __('The points system cannot be changed once a result has been entered.')]);
});

test('the points system is not changed when a result was entered after the request started', function () {
    $competition = Competition::factory()->create();
    CompetitionMatch::factory()->played()->create(['competition_id' => $competition->id]);

    $settings = CompetitionSettings::fromArray([...$competition->settings->toArray(), 'best_of' => 3, 'points_system' => 'games-won']);

    try {
        app(UpdateCompetitionSettings::class)->handle($competition, $settings);
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKeys(['best_of', 'points_system']);
    }

    expect($competition->refresh()->settings->pointsSystem)->toBe(PointsSystem::WinTwoLossZero);
});

function lockedFormatPayload(array $overrides = []): array
{
    return [
        'match_duration_minutes' => 20,
        'buffer_minutes' => 5,
        'min_rest_minutes' => 10,
        'break_duration_minutes' => 15,
        'use_pools' => '0',
        'max_matches_per_player_per_day' => 0,
        'best_of' => 5,
        'points_system' => 'win-2-loss-0',
        ...$overrides,
    ];
}

test('the match format cannot change once a result has been entered', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();
    CompetitionMatch::factory()->played()->create(['competition_id' => $competition->id]);

    $this->put(route('competitions.settings.update', $competition), lockedFormatPayload(['best_of' => 3]))
        ->assertSessionHasErrors(['best_of' => __('The match format cannot be changed once a result has been entered.')]);

    expect($competition->refresh()->settings->bestOf)->toBe(5);
});

test('the match format is not changed when a result was entered after the request started', function () {
    $competition = Competition::factory()->create();

    // De Form Request zag nog geen uitslag; deze test staat voor de eerste
    // uitslag die daarna werd vastgelegd. De Action kijkt onder de rijlock
    // opnieuw en weigert alsnog.
    CompetitionMatch::factory()->played()->create(['competition_id' => $competition->id]);

    $settings = CompetitionSettings::fromArray([...$competition->settings->toArray(), 'best_of' => 3]);

    expect(fn () => app(UpdateCompetitionSettings::class)->handle($competition, $settings))->toThrow(
        ValidationException::class,
        __('The match format cannot be changed once a result has been entered.'),
    );

    expect($competition->refresh()->settings->bestOf)->toBe(5);
});

test('other settings can still be saved with the same match format after a result', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();
    CompetitionMatch::factory()->played()->create(['competition_id' => $competition->id]);

    $this->put(route('competitions.settings.update', $competition), lockedFormatPayload(['match_duration_minutes' => 30]))
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('competitions.edit', $competition));

    expect($competition->refresh()->settings)
        ->matchDurationMinutes->toBe(30)
        ->bestOf->toBe(5);
});

test('the match format can be changed again when no result remains', function () {
    actingAsSettingsAdmin();
    $competition = Competition::factory()->create();
    $match = CompetitionMatch::factory()->played()->create(['competition_id' => $competition->id]);

    DB::table('matches')->where('id', $match->id)->update(['status' => MatchStatus::Pending->value]);

    $this->put(route('competitions.settings.update', $competition), lockedFormatPayload(['best_of' => 3]))
        ->assertSessionDoesntHaveErrors();

    expect($competition->refresh()->settings->bestOf)->toBe(3);
});

test('participants cannot update planning settings', function () {
    $competition = Competition::factory()->create();

    $this->actingAs(User::factory()->participant()->create())
        ->put(route('competitions.settings.update', $competition), [
            'match_duration_minutes' => 20,
            'buffer_minutes' => 5,
            'min_rest_minutes' => 10,
            'break_duration_minutes' => 15,
            'use_pools' => '0',
            'max_matches_per_player_per_day' => 0,
            'best_of' => 5,
            'points_system' => 'win-2-loss-0',
        ])->assertForbidden();
});

test('guests are redirected to the login page', function () {
    $competition = Competition::factory()->create();

    $this->put(route('competitions.settings.update', $competition), [])
        ->assertRedirect(route('login'));
});
