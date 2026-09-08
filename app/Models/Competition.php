<?php

namespace App\Models;

use App\Enums\CompetitionStatus;
use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description', 'location', 'starts_at', 'ends_at', 'status'])]
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
        'competitions',
        'dashboard',
        'email',
        'forgot-password',
        'invitation',
        'login',
        'logout',
        'no-competition',
        'register',
        'reset-password',
        'settings',
        'two-factor',
        'two-factor-challenge',
        'up',
        'user',
        'welcome',
    ];

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
        ];
    }
}
