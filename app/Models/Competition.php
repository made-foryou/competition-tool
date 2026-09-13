<?php

namespace App\Models;

use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Support\CompetitionSettings;
use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
     */
    public function availabilityReminderAvailableAt(): ?Carbon
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
     * Of er op dit moment een beschikbaarheidsherinnering verstuurd mag worden.
     */
    public function canSendAvailabilityReminder(): bool
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
