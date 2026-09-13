<?php

namespace App\Models;

use App\Enums\MatchStatus;
use Database\Factories\CompetitionMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eén wedstrijd tussen twee deelnemers binnen een competitie. De
 * wedstrijdenlijst wordt gegenereerd en gesynchroniseerd op basis van het
 * competitietype (zie CompetitionType); een gespeelde wedstrijd (status
 * Played) wordt door die sync nooit verwijderd. Het registreren van
 * uitslagen zelf volgt in een later issue — de score-kolommen liggen hier
 * alvast klaar.
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
#[Fillable(['competition_id', 'first_player_id', 'second_player_id', 'match_day_id', 'match_day_field_id', 'status', 'first_player_score', 'second_player_score'])]
class CompetitionMatch extends Model
{
    /** @use HasFactory<CompetitionMatchFactory> */
    use HasFactory;

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
