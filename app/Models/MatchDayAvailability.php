<?php

namespace App\Models;

use Database\Factories\MatchDayAvailabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Een deelnemer is aanwezig op een speeldag. Het bestaan van de rij ís de
 * beschikbaarheid; afwezigheid wordt niet apart vastgelegd.
 *
 * @property int $id
 * @property int $match_day_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MatchDay $matchDay
 * @property-read User $user
 */
#[Fillable(['match_day_id', 'user_id'])]
class MatchDayAvailability extends Model
{
    /** @use HasFactory<MatchDayAvailabilityFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<MatchDay, $this>
     */
    public function matchDay(): BelongsTo
    {
        return $this->belongsTo(MatchDay::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
