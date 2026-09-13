<?php

use App\Models\Competition;
use App\Models\User;
use App\Support\CompetitionSettings;
use Illuminate\Support\Facades\DB;
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
        ->and($competition->settings->maxMatchesPerPlayerPerDay)->toBe(0);
});

test('the withSettings factory state applies the given settings', function () {
    $settings = new CompetitionSettings(
        matchDurationMinutes: 30,
        bufferMinutes: 8,
        minRestMinutes: 12,
        breakDurationMinutes: 25,
        usePools: true,
        poolSize: 6,
        maxMatchesPerPlayerPerDay: 4,
    );

    $competition = Competition::factory()->withSettings($settings)->create();

    expect($competition->refresh()->settings)
        ->matchDurationMinutes->toBe(30)
        ->bufferMinutes->toBe(8)
        ->minRestMinutes->toBe(12)
        ->breakDurationMinutes->toBe(25)
        ->usePools->toBeTrue()
        ->poolSize->toBe(6)
        ->maxMatchesPerPlayerPerDay->toBe(4);
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
    ])->assertRedirect(route('competitions.edit', $competition))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Planning settings saved.')]);

    $settings = $competition->refresh()->settings;

    expect($settings->matchDurationMinutes)->toBe(25)
        ->and($settings->bufferMinutes)->toBe(10)
        ->and($settings->minRestMinutes)->toBe(15)
        ->and($settings->breakDurationMinutes)->toBe(20)
        ->and($settings->usePools)->toBeTrue()
        ->and($settings->poolSize)->toBe(5)
        ->and($settings->maxMatchesPerPlayerPerDay)->toBe(3);

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
        ->and($settings->bufferMinutes)->toBe(CompetitionSettings::DEFAULT_BUFFER_MINUTES);
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
        ])->assertForbidden();
});

test('guests are redirected to the login page', function () {
    $competition = Competition::factory()->create();

    $this->put(route('competitions.settings.update', $competition), [])
        ->assertRedirect(route('login'));
});
