<?php

namespace App\Models;

use App\Concerns\FormatsClockTime;
use App\Enums\MatchStatus;
use Carbon\CarbonInterface;
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
 * @property Carbon $availability_requested_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $fields_count
 * @property-read Competition $competition
 * @property-read Collection<int, MatchDayField> $fields
 * @property-read Collection<int, CompetitionMatch> $matches
 * @property-read Collection<int, CompetitionMatch> $pendingMatches
 * @property-read Collection<int, CompetitionMatch> $playedMatches
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
     *
     * Een verzette datum is voor deelnemers een nieuwe datum (#81). Stempel,
     * wissen en vrijgeven horen daarom altijd samen bij een datumwijziging,
     * via welk schrijfpad ook (formulier, seeder, tinker, een latere
     * bulkverschuiving). Zou alleen het stempel volgen, dan blijven de oude
     * "ja"-rijen staan voor een dag waarover ze niets zeggen.
     *
     * De `saving`-hook stempelt `availability_requested_at`: een invariant van
     * het model, zoals `created_at`. Alleen deze hook zet de kolom, bij het
     * aanmaken en wanneer de datum wijzigt. `isDirty('date')` is betrouwbaar
     * met de `date`-cast, omdat `originalIsEquivalent()` datums genormaliseerd
     * vergelijkt.
     *
     * De `updated`-hook wist bij een andere datum alle beschikbaarheidsrijen
     * van de speeldag en haalt al zijn openstaande wedstrijden uit de
     * planning, ook de vastgezette (`releasePendingMatches()`).
     * `wasChanged('date')` werkt hier omdat Eloquent `syncChanges()` vóór het
     * `updated`-event draait. De hook neemt geen lock: wie de datum wijzigt
     * waar de planner tegelijk kan draaien, gebruikt `UpdateMatchDay`.
     */
    protected static function booted(): void
    {
        static::saving(function (MatchDay $matchDay): void {
            if (! $matchDay->exists || $matchDay->isDirty('date')) {
                $matchDay->availability_requested_at = $matchDay->freshTimestamp();
            }
        });

        static::updated(function (MatchDay $matchDay): void {
            if ($matchDay->wasChanged('date')) {
                MatchDayAvailability::query()->where('match_day_id', $matchDay->id)->delete();

                $matchDay->releasePendingMatches();
            }
        });

        static::deleting(function (MatchDay $matchDay): void {
            $matchDay->releasePendingMatches();
        });
    }

    /**
     * Haalt alle openstaande wedstrijden op deze speeldag uit de planning, ook
     * de vastgezette: speeldag, tafel, tijden, vastzetten en reden worden leeg.
     * Gespeelde wedstrijden houden hun plek als historie. Geeft het aantal
     * vrijgegeven wedstrijden terug.
     */
    public function releasePendingMatches(): int
    {
        return $this->pendingMatches()->update([
            'match_day_id' => null,
            'match_day_field_id' => null,
            'starts_at' => null,
            'ends_at' => null,
            'pinned_at' => null,
            'scheduling_failure' => null,
        ]);
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
     * De openstaande wedstrijden die op deze speeldag ingepland staan, ook de
     * vastgezette. Eén plek voor de selectie die `releasePendingMatches()`
     * vrijgeeft, zodat de bewerkpagina vooraf precies die wedstrijden telt.
     *
     * @return HasMany<CompetitionMatch, $this>
     */
    public function pendingMatches(): HasMany
    {
        return $this->hasMany(CompetitionMatch::class)->where('status', MatchStatus::Pending->value);
    }

    /**
     * De gespeelde wedstrijden op deze speeldag. Die schuiven bij verzetten
     * mee als historie; de bewerkpagina telt ze om dat vooraf te melden.
     *
     * @return HasMany<CompetitionMatch, $this>
     */
    public function playedMatches(): HasMany
    {
        return $this->hasMany(CompetitionMatch::class)->where('status', MatchStatus::Played->value);
    }

    /**
     * Of deze speeldag nieuw is voor de deelnemer: deelnemers zijn er ná de
     * laatste inzending om gevraagd (`availability_requested_at`), omdat hij
     * daarna is aangemaakt of naar een andere datum verzet. `$submittedAt` is
     * zijn `availability_submitted_at` in deze competitie, dat nooit geleegd
     * wordt; ook na een lege inzending is een latere speeldag of verzetting
     * dus nieuw. Zonder inzending (`null`) is niets nieuw: dan is elke
     * speeldag nog niet ingevuld.
     *
     * Strikt groter dan: een speeldag uit dezelfde seconde als de inzending
     * is niet nieuw, net als bij `Competition::isCurrentSubmission()`.
     */
    public function isNewSince(?CarbonInterface $submittedAt): bool
    {
        return $submittedAt !== null && $this->availability_requested_at->greaterThan($submittedAt);
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
            'availability_requested_at' => 'datetime',
        ];
    }
}
