<?php

namespace App\Models;

use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Enums\MatchStatus;
use App\Support\CompetitionSettings;
use Carbon\CarbonInterface;
use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
     * Vraagt alle deelnemers opnieuw om hun beschikbaarheid: wist het
     * moment van inzending op de koppeltabel. De ingevulde speeldagen blijven
     * staan, zodat een deelnemer alleen de nieuwe dag hoeft aan te vinken.
     *
     * Eén regel, ongeacht de status: een nieuwe speeldag heropent de
     * beschikbaarheid altijd. Op een concept- of afgeronde competitie dwingt
     * `EnsureAvailabilityIsSubmitted` niets af, maar zodra de competitie
     * (weer) actief staat klopt de stand.
     *
     * @return int het aantal deelnemers dat opnieuw gevraagd wordt
     */
    public function reopenAvailability(): int
    {
        return DB::table('competition_user')
            ->where('competition_id', $this->id)
            ->whereNotNull('availability_submitted_at')
            ->update(['availability_submitted_at' => null]);
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
