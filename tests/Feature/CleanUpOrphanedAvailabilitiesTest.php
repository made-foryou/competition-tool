<?php

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;

test('the cleanup migration only removes availability of unlinked users', function () {
    $linked = Competition::factory()->create();
    $linkedDay = MatchDay::factory()->create(['competition_id' => $linked->id]);
    $unlinked = Competition::factory()->create();
    $unlinkedDay = MatchDay::factory()->create(['competition_id' => $unlinked->id]);

    $user = User::factory()->participant()->create();
    $linked->participants()->attach($user);

    MatchDayAvailability::create(['user_id' => $user->id, 'match_day_id' => $linkedDay->id]);
    MatchDayAvailability::create(['user_id' => $user->id, 'match_day_id' => $unlinkedDay->id]);

    $migration = require database_path('migrations/2026_09_15_103906_clean_up_orphaned_match_day_availabilities.php');

    $migration->up();

    expect($user->matchDayAvailabilities()->pluck('match_day_id')->all())->toBe([$linkedDay->id]);
});
