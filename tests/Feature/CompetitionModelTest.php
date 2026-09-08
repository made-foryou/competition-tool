<?php

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Support\Facades\Route;

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

test('reserved slugs cover every static top-level route segment', function () {
    $segments = collect(Route::getRoutes())
        ->map(function ($route): string {
            $uri = trim($route->uri(), '/');

            return $uri === '' ? '' : explode('/', $uri)[0];
        })
        ->filter(fn (string $segment): bool => $segment !== ''
            && ! str_starts_with($segment, '{')
            && ! str_starts_with($segment, '_')
            && ! str_starts_with($segment, '.'))
        ->unique()
        ->values();

    $missing = $segments->reject(
        fn (string $segment): bool => in_array($segment, Competition::RESERVED_SLUGS, true)
    );

    expect($missing)->toBeEmpty(
        'Ontbrekende top-level route-segmenten in Competition::RESERVED_SLUGS: '.$missing->implode(', ')
    );
});
