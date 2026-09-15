<?php

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\Invitation;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\MatchDayField;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;

/**
 * Eén regel voor alle modellen: een foreign key die uit de route of uit de
 * ingelogde gebruiker volgt, is niet mass-assignable. Die koppeling leg je via
 * de relatie of met forceFill(), zodat een extra sleutel in de request-body de
 * eigenaar of de context van een rij nooit kan verzetten.
 *
 * Buiten productie staat preventSilentlyDiscardingAttributes aan, dus fill()
 * gooit op zo'n sleutel in plaats van hem stil weg te gooien.
 */
test('a context foreign key is not mass-assignable', function (string $model, string $attribute) {
    expect(fn () => (new $model)->fill([$attribute => 1]))
        ->toThrow(MassAssignmentException::class, $attribute);
})->with([
    'availability belongs to the signed-in participant' => [MatchDayAvailability::class, 'user_id'],
    'match day belongs to the competition in the route' => [MatchDay::class, 'competition_id'],
    'field belongs to the match day in the route' => [MatchDayField::class, 'match_day_id'],
    'invitation records its sender' => [Invitation::class, 'invited_by'],
    'invitation belongs to the competition in the route' => [Invitation::class, 'competition_id'],
    'match belongs to the competition in the route' => [CompetitionMatch::class, 'competition_id'],
    'match is scheduled on a match day by the generator' => [CompetitionMatch::class, 'match_day_id'],
    'match field is assigned by the generator' => [CompetitionMatch::class, 'match_day_field_id'],
    'first player is assigned by the generator' => [CompetitionMatch::class, 'first_player_id'],
    'second player is assigned by the generator' => [CompetitionMatch::class, 'second_player_id'],
    'match start time is set by the planner' => [CompetitionMatch::class, 'starts_at'],
    'match end time is set by the planner' => [CompetitionMatch::class, 'ends_at'],
]);

/**
 * Hetzelfde net voor attributen die geen foreign key zijn maar wel rechten of
 * procesvoortgang bepalen. `ProfileController` vult het profiel met
 * `fill($request->validated())`, dus een rol die per ongeluk fillable wordt,
 * is direct een escalatie naar beheerder. Een uitnodigingstoken dat de
 * genodigde zelf mag meesturen, is hetzelfde probleem een laag verderop: de
 * sleutel waarmee de uitnodiging wordt opgezocht, komt dan uit de request.
 */
test('a privilege or process attribute is not mass-assignable', function (string $model, string $attribute, mixed $value) {
    expect(fn () => (new $model)->fill([$attribute => $value]))
        ->toThrow(MassAssignmentException::class, $attribute);
})->with([
    'a user cannot promote themselves' => [User::class, 'role', UserRole::Admin],
    'a reminder timestamp is set by the send action only' => [Competition::class, 'availability_reminder_sent_at', '2026-01-01 00:00:00'],
    'an invitee cannot choose their own token' => [Invitation::class, 'token', 'gekozen-token'],
    'an invitation is marked accepted by the accept flow only' => [Invitation::class, 'accepted_at', '2026-01-01 00:00:00'],
    'a match is pinned by the move action only' => [CompetitionMatch::class, 'pinned_at', '2026-01-01 00:00:00'],
    'a scheduling failure is recorded by the planner only' => [CompetitionMatch::class, 'scheduling_failure', 'no_capacity'],
]);
