<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
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
use Laravel\Fortify\Contracts\PasskeyUser;
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
     * Heeft de gebruiker zijn beschikbaarheid voor deze competitie ingediend?
     * Een lege selectie telt ook als ingediend, vandaar de losse kolom op de
     * koppeltabel.
     */
    public function hasSubmittedAvailabilityFor(Competition $competition): bool
    {
        return $this->competitions()
            ->whereKey($competition->id)
            ->wherePivotNotNull('availability_submitted_at')
            ->exists();
    }
}
