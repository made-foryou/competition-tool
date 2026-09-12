<?php

use App\Models\Competition;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the display name falls back to the real name', function () {
    $user = User::factory()->create(['name' => 'Menno Tempelaar', 'nickname' => null]);

    expect($user->display_name)->toBe('Menno Tempelaar');
});

test('a nickname replaces the display name', function () {
    $user = User::factory()->create(['name' => 'Menno Tempelaar', 'nickname' => 'MadMax']);

    expect($user->display_name)->toBe('MadMax');
});

test('the display name is shared with the frontend', function () {
    $user = User::factory()->withTwoFactor()->create(['nickname' => 'MadMax']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.display_name', 'MadMax'));
});

test('participants see each other by nickname and never the real name', function () {
    $competition = Competition::factory()->create();

    $viewer = User::factory()->participant()->create(['name' => 'Zander', 'nickname' => null]);
    $other = User::factory()->participant()->create([
        'name' => 'Sanne de Vries',
        'nickname' => 'Speedy',
    ]);

    $competition->participants()->attach($viewer, ['availability_submitted_at' => now()]);
    $competition->participants()->attach($other, ['availability_submitted_at' => now()]);

    $response = $this->actingAs($viewer)->get(route('competition.dashboard', $competition));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('participants', 2)
        ->where('participants.0.name', 'Speedy'),
    );

    expect($response->content())->not->toContain('Sanne de Vries');
});

test('admins see both the nickname and the real name', function () {
    $competition = Competition::factory()->create();
    $participant = User::factory()->participant()->create([
        'name' => 'Sanne de Vries',
        'nickname' => 'Speedy',
    ]);
    $competition->participants()->attach($participant);

    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(route('competitions.edit', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('participants.0.name', 'Sanne de Vries')
            ->where('participants.0.nickname', 'Speedy')
            ->where('participants.0.display_name', 'Speedy'),
        );
});

test('participants are sorted by display name', function () {
    $competition = Competition::factory()->create();

    $competition->participants()->attach(
        User::factory()->participant()->create(['name' => 'Anna', 'nickname' => 'Zorro']),
    );
    $competition->participants()->attach(
        User::factory()->participant()->create(['name' => 'Bob', 'nickname' => null]),
    );

    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(route('competitions.edit', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('participants.0.name', 'Bob')
            ->where('participants.1.name', 'Anna'),
        );
});

test('an empty nickname is stored as null', function () {
    $user = User::factory()->withTwoFactor()->create(['nickname' => 'MadMax']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'nickname' => '',
        'email' => $user->email,
    ])->assertRedirect();

    expect($user->refresh()->nickname)->toBeNull()
        ->and($user->display_name)->toBe($user->name);
});

test('a participant can set a nickname in their competition settings', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user, ['availability_submitted_at' => now()]);

    $this->actingAs($user)->patch(route('competition.settings.profile.update', $competition), [
        'name' => $user->name,
        'nickname' => 'Speedy',
        'email' => $user->email,
    ])->assertRedirect();

    expect($user->refresh()->nickname)->toBe('Speedy');
});
