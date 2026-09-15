<?php

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->withTwoFactor()->create();

    $this->actingAs($this->admin);
});

test('a participant can be promoted to administrator', function () {
    $competition = Competition::factory()->create();
    $participant = User::factory()->participant()->create();
    $competition->participants()->attach($participant);

    $this->patch(route('competitions.participants.role', [$competition, $participant]), [
        'role' => UserRole::Admin->value,
    ])->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __(':name is now an administrator.', ['name' => $participant->display_name]),
        ]);

    expect($participant->refresh()->role)->toBe(UserRole::Admin);
});

test('an administrator can be demoted while another administrator remains', function () {
    $competition = Competition::factory()->create();
    $other = User::factory()->withTwoFactor()->create();
    $competition->participants()->attach($other);

    expect($other->isAdmin())->toBeTrue();

    $this->patch(route('competitions.participants.role', [$competition, $other]), [
        'role' => UserRole::Participant->value,
    ])->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __(':name is no longer an administrator.', ['name' => $other->display_name]),
        ]);

    expect($other->refresh()->role)->toBe(UserRole::Participant);
});

test('the last administrator cannot be demoted', function () {
    $competition = Competition::factory()->create();
    $competition->participants()->attach($this->admin);

    $this->patch(route('competitions.participants.role', [$competition, $this->admin]), [
        'role' => UserRole::Participant->value,
    ])->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => __('You cannot remove the last administrator.'),
        ]);

    expect($this->admin->refresh()->role)->toBe(UserRole::Admin);
});

test('an administrator cannot change their own role', function () {
    $competition = Competition::factory()->create();
    User::factory()->withTwoFactor()->create();
    $competition->participants()->attach($this->admin);

    $this->patch(route('competitions.participants.role', [$competition, $this->admin]), [
        'role' => UserRole::Participant->value,
    ])->assertForbidden();

    expect($this->admin->refresh()->role)->toBe(UserRole::Admin);
});

test('a user outside this competition cannot be changed', function () {
    $competition = Competition::factory()->create();
    $outsider = User::factory()->participant()->create();

    $this->patch(route('competitions.participants.role', [$competition, $outsider]), [
        'role' => UserRole::Admin->value,
    ])->assertNotFound();

    expect($outsider->refresh()->role)->toBe(UserRole::Participant);
});

test('an unknown role is rejected', function () {
    $competition = Competition::factory()->create();
    $participant = User::factory()->participant()->create();
    $competition->participants()->attach($participant);

    $this->patch(route('competitions.participants.role', [$competition, $participant]), [
        'role' => 'superadmin',
    ])->assertSessionHasErrors('role');

    expect($participant->refresh()->role)->toBe(UserRole::Participant);
});

test('setting the role a participant already has does not claim a demotion', function () {
    $competition = Competition::factory()->create();
    $participant = User::factory()->participant()->create();
    $competition->participants()->attach($participant);

    $this->patch(route('competitions.participants.role', [$competition, $participant]), [
        'role' => UserRole::Participant->value,
    ])->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __(':name is a participant.', ['name' => $participant->display_name]),
        ]);

    expect($participant->refresh()->role)->toBe(UserRole::Participant);
});

test('participants cannot change roles', function () {
    $competition = Competition::factory()->create();
    $participant = User::factory()->participant()->create();
    $competition->participants()->attach($participant);

    $this->actingAs(User::factory()->participant()->create())
        ->patch(route('competitions.participants.role', [$competition, $participant]), [
            'role' => UserRole::Admin->value,
        ])->assertForbidden();

    expect($participant->refresh()->role)->toBe(UserRole::Participant);
});
