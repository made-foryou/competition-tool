<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Gedeelde `H:i`-accessor/mutator voor `time`-kolommen zoals `starts_at` en
 * `ends_at` op `MatchDay` en `CompetitionMatch`.
 */
trait FormatsClockTime
{
    /**
     * De kloktijd altijd als `H:i` naar buiten, altijd als `H:i:s` naar de
     * database. MySQL geeft een `time`-kolom als `09:00:00` terug en SQLite
     * exact wat er is weggeschreven; zonder dit paar verschillen de twee.
     * `<input type="time">` en `date_format:H:i` willen beide `09:00`.
     * Null-veilig, want de kolom mag ongepland/leeg zijn.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function clockTimeAttribute(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : substr($value, 0, 5),
            set: fn (?string $value): ?string => $value === null ? null : substr($value, 0, 5).':00',
        );
    }
}
