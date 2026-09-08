<?php

use App\Enums\UserRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the shared auth user prop includes the role', function () {
    $admin = User::factory()->withTwoFactor()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', UserRole::Admin->value),
        );

    $participant = User::factory()->participant()->create();

    $this->actingAs($participant)
        ->get(route('competition.none'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', UserRole::Participant->value),
        );
});
