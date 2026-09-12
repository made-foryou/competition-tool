<?php

namespace App\Models;

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
 */
#[Fillable(['competition_id', 'date', 'starts_at', 'ends_at'])]
class MatchDay extends Model
{
    /** @use HasFactory<MatchDayFactory> */
    use HasFactory;

    /**
     * Het maximum aantal speelvelden dat bij het aanmaken van een speeldag in
     * één keer gegenereerd mag worden.
     */
    public const int MAX_FIELDS = 20;

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
     * De begintijd altijd als `H:i` naar buiten, altijd als `H:i:s` naar de
     * database. MySQL geeft een `time`-kolom als `09:00:00` terug en SQLite
     * exact wat er is weggeschreven; zonder dit paar verschillen de twee.
     * `<input type="time">` en `date_format:H:i` willen beide `09:00`.
     *
     * @return Attribute<string, string>
     */
    protected function startsAt(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): string => substr($value, 0, 5),
            set: fn (string $value): string => substr($value, 0, 5).':00',
        );
    }

    /**
     * De eindtijd, zie startsAt().
     *
     * @return Attribute<string, string>
     */
    protected function endsAt(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): string => substr($value, 0, 5),
            set: fn (string $value): string => substr($value, 0, 5).':00',
        );
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
