<?php

namespace App\Models;

use App\Concerns\FormatsClockTime;
use App\Enums\MatchStatus;
use App\Enums\SchedulingFailure;
use Database\Factories\CompetitionMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Eén wedstrijd tussen twee deelnemers binnen een competitie. De
 * wedstrijdenlijst wordt gegenereerd en gesynchroniseerd op basis van het
 * competitietype (zie CompetitionType); een gespeelde wedstrijd (status
 * Played) wordt door die sync nooit verwijderd. Het registreren van
 * uitslagen zelf volgt in een later issue — de score-kolommen liggen hier
 * alvast klaar. Speeldag, tafel en tijden worden gevuld door de planner (zie
 * `ScheduleCompetitionMatches`, later issue) via de query builder.
 *
 * Alleen de score-kolommen zijn fillable: dat is de verdediging voor het
 * toekomstige uitslagen-issue, zodat request-invoer nooit per ongeluk de
 * competitie- of spelerskoppeling, de planning of het vastzetten kan
 * overschrijven. De sync en de planner schrijven via insertOrIgnore/de query
 * builder en factories omzeilen de guarding, dus die raken deze beperking
 * niet.
 *
 * @property int $id
 * @property int $competition_id
 * @property int $first_player_id
 * @property int $second_player_id
 * @property int|null $match_day_id
 * @property int|null $match_day_field_id
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property Carbon|null $pinned_at
 * @property MatchStatus $status
 * @property SchedulingFailure|null $scheduling_failure
 * @property int|null $first_player_score
 * @property int|null $second_player_score
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Competition $competition
 * @property-read User $firstPlayer
 * @property-read User $secondPlayer
 * @property-read MatchDay|null $matchDay
 * @property-read MatchDayField|null $matchDayField
 */
#[Fillable(['first_player_score', 'second_player_score'])]
class CompetitionMatch extends Model
{
    /**
     * `starts_at`/`ends_at` zijn hier, anders dan op `MatchDay`, nullable
     * (nog niet ingepland). De accessor/mutator komt via een trait-alias
     * binnen in plaats van via een `return $this->clockTimeAttribute();`
     * wrapper: phpstan/larastan verwart bij een nullable generic Attribute
     * de teruggegeven en de gedeclareerde generic van zo'n wrapper met
     * elkaar (een self-conflict op een op zich identieke, non-covariante
     * `Attribute<TGet, TSet>`); de trait-methode rechtstreeks onder de
     * property-naam aliassen omzeilt die valse-positieve zonder de
     * substr-logica te dupliceren of de fout te onderdrukken.
     */
    use FormatsClockTime {
        clockTimeAttribute as protected startsAt;
        clockTimeAttribute as protected endsAt;
    }

    /** @use HasFactory<CompetitionMatchFactory> */
    use HasFactory;

    /**
     * Normaliseert elk spelerspaar naar zijn canonieke vorm (laagste user-id
     * als first player) vóór het opslaan. De unique-index op
     * (competition_id, first_player_id, second_player_id) kan spiegelparen
     * (A-B naast B-A) namelijk niet uitsluiten; deze hook garandeert de
     * canoniciteit voor alle toekomstige schrijvers via het model (zoals het
     * latere uitslagen-issue). Let op: SyncCompetitionMatches schrijft via
     * insertOrIgnore en raakt deze model-events dus niet — dat is oké, want
     * de sync bouwt zijn paren zelf al canoniek op.
     */
    protected static function booted(): void
    {
        static::saving(function (CompetitionMatch $match): void {
            if ($match->first_player_id === $match->second_player_id) {
                throw new InvalidArgumentException('A match cannot pair a player against themselves.');
            }

            if ($match->first_player_id > $match->second_player_id) {
                [$match->first_player_id, $match->second_player_id] = [$match->second_player_id, $match->first_player_id];
            }
        });
    }

    /**
     * Laravel zou van deze modelnaam `competition_matches` afleiden; de
     * tabel heet bewust `matches` (`match` zelf is een reserved word in
     * PHP en dus geen bruikbare modelnaam).
     *
     * @var string
     */
    protected $table = 'matches';

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function firstPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_player_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function secondPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_player_id');
    }

    /**
     * @return BelongsTo<MatchDay, $this>
     */
    public function matchDay(): BelongsTo
    {
        return $this->belongsTo(MatchDay::class);
    }

    /**
     * @return BelongsTo<MatchDayField, $this>
     */
    public function matchDayField(): BelongsTo
    {
        return $this->belongsTo(MatchDayField::class);
    }

    /**
     * Of de uitslag van deze wedstrijd al geregistreerd is.
     */
    public function isPlayed(): bool
    {
        return $this->status === MatchStatus::Played;
    }

    /**
     * Of deze wedstrijd volledig ingepland is: speeldag, tafel én begintijd
     * zijn alle drie gevuld. Een wedstrijd waarvan de tafel achteraf
     * verwijderd is (zie de `deleting`-hooks op `MatchDay`/`MatchDayField`)
     * telt dus als ongepland, ook al staat `match_day_id` nog wel.
     */
    public function isScheduled(): bool
    {
        return $this->match_day_id !== null
            && $this->match_day_field_id !== null
            && $this->starts_at !== null;
    }

    /**
     * Of deze wedstrijd handmatig vastgezet is. Herplannen laat een
     * vastgezette wedstrijd altijd op zijn plek staan.
     */
    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }

    /**
     * Of deze wedstrijd door herplannen nooit verplaatst wordt: gespeeld of
     * vastgezet.
     */
    public function isLocked(): bool
    {
        return $this->isPlayed() || $this->isPinned();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
            'pinned_at' => 'datetime',
            'scheduling_failure' => SchedulingFailure::class,
        ];
    }
}
