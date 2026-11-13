<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\Features;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $nickname
 * @property string $email
 * @property UserRole $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $display_name
 */
#[Fillable(['name', 'nickname', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * De weergavenaam gaat mee in elke serialisatie, zodat de frontend hem
     * overal kan gebruiken zonder dat elke controller hem apart meestuurt.
     *
     * @var list<string>
     */
    protected $appends = ['display_name'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
        ];
    }

    /**
     * De naam waaronder de gebruiker getoond wordt. Deelnemers kunnen onder
     * een nickname spelen om hun echte naam prive te houden; is die leeg, dan
     * valt het terug op de naam.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->nickname ?? $this->name);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Moet deze gebruiker nog een tweede factor instellen voordat hij de
     * applicatie in mag? Alleen beheerders zijn daartoe verplicht, en alleen
     * zolang 2FA of passkeys als Fortify-feature aan staan.
     */
    public function requiresSecondFactorSetup(): bool
    {
        if (! $this->isAdmin()) {
            return false;
        }

        if (! Features::enabled(Features::twoFactorAuthentication()) && ! Features::enabled(Features::passkeys())) {
            return false;
        }

        return ! $this->hasEnabledTwoFactorAuthentication() && ! $this->hasPasskeysEnabled();
    }

    /**
     * @return BelongsToMany<Competition, $this>
     */
    public function competitions(): BelongsToMany
    {
        return $this->belongsToMany(Competition::class)
            ->withPivot('availability_submitted_at')
            ->withTimestamps();
    }

    /**
     * @return HasMany<MatchDayAvailability, $this>
     */
    public function matchDayAvailabilities(): HasMany
    {
        return $this->hasMany(MatchDayAvailability::class);
    }

    /**
     * Is de beschikbaarheid van de gebruiker voor deze competitie actueel?
     * Dat is zo als hij heeft ingediend en er daarna geen speeldag meer is
     * bijgekomen of verzet (`Competition::isCurrentSubmission()`). Een lege selectie
     * telt ook als inzending, vandaar de losse kolom op de koppeltabel. Wie
     * geen deelnemer is, heeft niets ingediend.
     */
    public function hasSubmittedAvailabilityFor(Competition $competition): bool
    {
        return Competition::isCurrentSubmission(
            $this->availabilitySubmittedAtFor($competition),
            $competition->latestAvailabilityRequestedAt(),
        );
    }

    /**
     * Heeft de gebruiker ooit beschikbaarheid voor deze competitie ingediend,
     * ook als die inmiddels niet meer actueel is? Dan zijn zijn lege vakjes
     * bewuste antwoorden en geen "nog niets ingevuld".
     */
    public function hasEverSubmittedAvailabilityFor(Competition $competition): bool
    {
        return $this->availabilitySubmittedAtFor($competition) !== null;
    }

    /**
     * Het moment van de laatste inzending voor deze competitie, of `null` als
     * de gebruiker nooit indiende of geen deelnemer is.
     */
    public function availabilitySubmittedAtFor(Competition $competition): ?CarbonInterface
    {
        return Competition::availabilitySubmittedAtFromPivot(DB::table('competition_user')
            ->where('competition_id', $competition->id)
            ->where('user_id', $this->id)
            ->value('availability_submitted_at'));
    }
}
