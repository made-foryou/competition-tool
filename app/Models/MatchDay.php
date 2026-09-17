<?php

namespace App\Models;

use App\Concerns\FormatsClockTime;
use App\Enums\MatchStatus;
use Database\Factories\MatchDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $competition_id
 * @property Carbon $date
 * @property string $starts_at
 * @property string $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $fields_count
 * @property-read Competition $competition
 * @property-read Collection<int, MatchDayField> $fields
 * @property-read Collection<int, CompetitionMatch> $matches
 */
#[Fillable(['date', 'starts_at', 'ends_at'])]
class MatchDay extends Model
{
    use FormatsClockTime;

    /** @use HasFactory<MatchDayFactory> */
    use HasFactory;

    /**
     * Het maximum aantal speelvelden dat bij het aanmaken van een speeldag in
     * één keer gegenereerd mag worden.
     */
    public const int MAX_FIELDS = 20;

    /**
     * `nullOnDelete` op de foreign keys zet bij het verwijderen van een
     * speeldag alleen `match_day_id`/`match_day_field_id` op null; de
     * ingeplande tijden en het vastzetten zouden blijven staan. Voor een
     * openstaande wedstrijd is dat een spookplanning op een niet meer
     * bestaande speeldag, dus deze hook maakt de hele planning leeg
     * (speeldag, tafel, tijden, vastzetten, reden). Gespeelde wedstrijden
     * houden hun tijden als historie. De DB-cascade speeldag → tafels vuurt
     * geen Eloquent-events op de tafels, dus deze hook dekt die zelf mee af
     * (de losse hook op `MatchDayField` is er voor het los verwijderen van
     * één tafel).
     */
    protected static function booted(): void
    {
        static::deleting(function (MatchDay $matchDay): void {
            CompetitionMatch::query()
                ->where('match_day_id', $matchDay->id)
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
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return HasMany<MatchDayField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(MatchDayField::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<CompetitionMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(CompetitionMatch::class)->orderBy('starts_at')->orderBy('match_day_field_id')->orderBy('id');
    }

    /**
     * @return Attribute<string, string>
     */
    protected function startsAt(): Attribute
    {
        return $this->clockTimeAttribute();
    }

    /**
     * @return Attribute<string, string>
     */
    protected function endsAt(): Attribute
    {
        return $this->clockTimeAttribute();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
