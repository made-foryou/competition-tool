<?php

namespace App\Models;

use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Enums\MatchStatus;
use App\Support\CompetitionSettings;
use Carbon\CarbonInterface;
use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $location
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property CompetitionStatus $status
 * @property CompetitionType $type
 * @property CompetitionSettings $settings
 * @property Carbon|null $availability_reminder_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, MatchDay> $matchDays
 * @property-read Collection<int, CompetitionMatch> $matches
 */
#[Fillable(['name', 'slug', 'description', 'location', 'starts_at', 'ends_at', 'status', 'type', 'settings'])]
class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use HasFactory;

    /**
     * Slugs die botsen met bestaande top-level routes en dus nooit als
     * competitie-slug gebruikt mogen worden.
     *
     * @var list<string>
     */
    public const array RESERVED_SLUGS = [
        'build',
        'competitions',
        'dashboard',
        'email',
        'forgot-password',
        'invitation',
        'login',
        'logout',
        'no-competition',
        'passkeys',
        'register',
        'reset-password',
        'settings',
        'storage',
        'two-factor',
        'two-factor-challenge',
        'up',
        'user',
        'welcome',
    ];

    /**
     * Het anti-spamvenster per competitie: na het versturen van een
     * beschikbaarheidsherinnering mag er dit aantal uren lang geen nieuwe
     * ronde verstuurd worden.
     */
    public const int AVAILABILITY_REMINDER_INTERVAL_HOURS = 24;

    /**
     * Het moment waarop er weer een beschikbaarheidsherinnering verstuurd mag
     * worden, of `null` wanneer dat nu al mag — dus wanneer er nog nooit een
     * herinnering is verstuurd of het venster inmiddels verstreken is.
     *
     * `availability_reminder_sent_at` staat bewust niet in het `#[Fillable]`-
     * attribuut: die timestamp wordt uitsluitend door de verzendactie gezet en
     * nooit via mass assignment vanuit een request.
     *
     * Het retourtype is `CarbonInterface` en niet `Carbon`: de applicatie zet
     * met `Date::use(CarbonImmutable::class)` alle datumcasts op
     * `CarbonImmutable`, en dat is geen subklasse van `Illuminate\Support\Carbon`.
     */
    public function availabilityReminderAvailableAt(): ?CarbonInterface
    {
        if ($this->availability_reminder_sent_at === null) {
            return null;
        }

        $availableAt = $this->availability_reminder_sent_at->copy()
            ->addHours(self::AVAILABILITY_REMINDER_INTERVAL_HOURS);

        if ($availableAt->isPast()) {
            return null;
        }

        return $availableAt;
    }

    /**
     * Of het anti-spamvenster van 24 uur op dit moment open staat.
     *
     * Dit zegt uitsluitend iets over het tijdvenster: niet over de status van
     * de competitie, of er speeldagen zijn, en niet of er überhaupt nog
     * deelnemers openstaan. Die voorwaarden staan als aparte checks in
     * CompetitionAvailabilityReminderController.
     */
    public function availabilityReminderWindowIsOpen(): bool
    {
        return $this->availabilityReminderAvailableAt() === null;
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Of deze gebruiker aan de competitie gekoppeld is als deelnemer.
     */
    public function hasParticipant(User $user): bool
    {
        return $this->participants()->whereKey($user->getKey())->exists();
    }

    /**
     * Het nieuwste moment waarop om beschikbaarheid gevraagd is (de nieuwste
     * aangemaakte of verzette speeldag), of `null` zonder speeldagen. Een
     * inzending die ouder is, telt niet meer als actueel
     * (`isCurrentSubmission()`).
     */
    public function latestAvailabilityRequestedAt(): ?CarbonInterface
    {
        $latest = MatchDay::query()
            ->where('competition_id', $this->id)
            ->max('availability_requested_at');

        return $latest === null ? null : Date::parse($latest);
    }

    /**
     * Of een inzending van de beschikbaarheid nog actueel is: er is ingediend
     * en er is daarna geen speeldag meer bijgekomen of verzet. `availability_submitted_at`
     * wordt nooit geleegd; een nieuwe of verzette speeldag maakt een oudere inzending
     * vanzelf niet-actueel, en het verwijderen van die speeldag herstelt dat.
     *
     * `>=` en niet `>`: een inzending in dezelfde seconde als het nieuwste verzoek
     * het nieuwste beschikbaarheidsverzoek blijft geldig. Tijdstempels hebben secondeprecisie,
     * dus binnen die seconde valt de volgorde niet te bepalen.
     *
     * Dezelfde regel, in SQL: `constrainToAwaitingAvailability()`.
     */
    public static function isCurrentSubmission(?CarbonInterface $submittedAt, ?CarbonInterface $latestAvailabilityRequestedAt): bool
    {
        return $submittedAt !== null
            && ($latestAvailabilityRequestedAt === null || $submittedAt->greaterThanOrEqualTo($latestAvailabilityRequestedAt));
    }

    /**
     * Beperkt een relatie over de koppeltabel `competition_user` tot de
     * deelnames waarvoor (opnieuw) beschikbaarheid ingediend moet worden: er
     * is nooit ingediend, of de inzending is ouder dan het nieuwste
     * beschikbaarheidsverzoek van die competitie. Werkt voor `$user->competitions()` en
     * `$competition->participants()`, omdat de subquery via
     * `competition_user.competition_id` correleert.
     *
     * Strikt kleiner dan (`<`): een inzending in dezelfde seconde als de
     * nieuwste beschikbaarheidsverzoek blijft geldig. Zonder speeldagen levert de subquery
     * `NULL` op en is `x < NULL` in SQL nooit waar, dus dan moet alleen wie
     * nooit indiende nog iets doen.
     *
     * Dezelfde regel, in PHP: `isCurrentSubmission()`.
     *
     * @template TRelation of BelongsToMany<*, *, *, *>
     *
     * @param  TRelation  $query
     * @return TRelation
     */
    public static function constrainToAwaitingAvailability(BelongsToMany $query): BelongsToMany
    {
        return $query->where(fn (Builder $pending) => $pending
            ->whereNull('competition_user.availability_submitted_at')
            ->orWhere('competition_user.availability_submitted_at', '<', fn (QueryBuilder $latestMatchDay) => $latestMatchDay
                ->selectRaw('max(match_days.availability_requested_at)')
                ->from('match_days')
                ->whereColumn('match_days.competition_id', 'competition_user.competition_id')));
    }

    /**
     * Het inzendmoment zoals het van de koppeltabel komt, als datum.
     * `competition_user` heeft geen pivot-model met casts, dus de ruwe waarde
     * is een string of `null`. Alle code die het moment in PHP nodig heeft,
     * parst het hiermee.
     */
    public static function availabilitySubmittedAtFromPivot(?string $value): ?CarbonInterface
    {
        return $value === null ? null : Date::parse($value);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return HasMany<MatchDay, $this>
     */
    public function matchDays(): HasMany
    {
        return $this->hasMany(MatchDay::class)->orderBy('date')->orderBy('starts_at');
    }

    /**
     * @return HasMany<CompetitionMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(CompetitionMatch::class)->orderBy('id');
    }

    /**
     * Of er al minstens één uitslag geregistreerd is. Bepaalt o.a. dat het
     * wedstrijdformat vergrendeld is.
     */
    public function hasResults(): bool
    {
        return $this->matches()->where('status', MatchStatus::Played->value)->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'status' => CompetitionStatus::class,
            'type' => CompetitionType::class,
            'settings' => CompetitionSettings::class,
            'availability_reminder_sent_at' => 'datetime',
        ];
    }
}
