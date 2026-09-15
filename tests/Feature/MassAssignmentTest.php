<?php

use App\Models\Invitation;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\MatchDayField;

/**
 * Eén regel voor alle modellen: een foreign key die uit de route of uit de
 * ingelogde gebruiker volgt, is niet mass-assignable. Die koppeling leg je via
 * de relatie of met forceFill(), zodat een extra sleutel in de request-body de
 * eigenaar of de context van een rij nooit kan verzetten.
 */
test('a context foreign key is not mass-assignable', function (string $model, string $attribute) {
    $instance = (new $model)->fill([$attribute => 1]);

    expect($instance->getAttributes())->not->toHaveKey($attribute);
})->with([
    'availability belongs to the signed-in participant' => [MatchDayAvailability::class, 'user_id'],
    'match day belongs to the competition in the route' => [MatchDay::class, 'competition_id'],
    'field belongs to the match day in the route' => [MatchDayField::class, 'match_day_id'],
    'invitation records its sender' => [Invitation::class, 'invited_by'],
    'invitation belongs to the competition in the route' => [Invitation::class, 'competition_id'],
]);
