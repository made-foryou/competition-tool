<?php

namespace App\Models;

use App\Enums\MatchStatus;
use Database\Factories\MatchDayFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $match_day_id
 * @property string $name
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MatchDay $matchDay
 */
#[Fillable(['name', 'position'])]
class MatchDayField extends Model
{
    /** @use HasFactory<MatchDayFieldFactory> */
    use HasFactory;

    /**
     * Zelfde opruiming als op `MatchDay::booted()`, maar dan voor het los
     * verwijderen van één tafel: een wedstrijd zonder tafel is domweg
     * ongepland, dus de hele planning van de openstaande wedstrijden op deze
     * tafel gaat leeg.
     */
    protected static function booted(): void
    {
        static::deleting(function (MatchDayField $field): void {
            CompetitionMatch::query()
                ->where('match_day_field_id', $field->id)
                ->where('status', MatchStatus::Pending->value)
                ->update([
                    'match_day_id' => null,
                    'match_day_field_id' => null,
                    'starts_at' => null,
                    'ends_at' => null,
                    'pinned_at' => null,
                    'scheduling_failure' => null,
                ]);
        });
    }

    /**
     * @return BelongsTo<MatchDay, $this>
     */
    public function matchDay(): BelongsTo
    {
        return $this->belongsTo(MatchDay::class);
    }
}
