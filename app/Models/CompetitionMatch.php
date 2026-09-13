<?php

namespace App\Models;

use App\Enums\MatchStatus;
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
 * alvast klaar.
 *
 * Alleen de score-kolommen zijn fillable: dat is de verdediging voor het
 * toekomstige uitslagen-issue, zodat request-invoer nooit per ongeluk de
 * competitie- of spelerskoppeling kan overschrijven. De sync schrijft via
 * insertOrIgnore en factories omzeilen de guarding, dus die raken deze
 * beperking niet.
 *
 * @property int $id
 * @property int $competition_id
 * @property int $first_player_id
 * @property int $second_player_id
 * @property int|null $match_day_id
 * @property int|null $match_day_field_id
 * @property MatchStatus $status
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
        ];
    }
}
