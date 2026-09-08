<?php

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\User;

test('a competition casts its fields and has participants', function () {
    $competition = Competition::factory()->create(['name' => 'Voorjaarstoernooi 2026']);
    $user = User::factory()->participant()->create();

    $competition->participants()->attach($user);

    expect($competition->status)->toBe(CompetitionStatus::Active)
        ->and($competition->starts_at->toDateString())->toBeString()
        ->and($competition->participants()->count())->toBe(1)
        ->and($user->competitions()->count())->toBe(1);
});

test('factory states set the status', function () {
    expect(Competition::factory()->draft()->create()->status)->toBe(CompetitionStatus::Draft)
        ->and(Competition::factory()->finished()->create()->status)->toBe(CompetitionStatus::Finished);
});

test('deleting a competition removes the pivot rows but keeps the users', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $competition->delete();

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and($user->competitions()->count())->toBe(0);
});
