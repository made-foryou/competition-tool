<?php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Planningsinstellingen van een competitie: hoe lang een wedstrijd en de
 * pauze ertussen duren, hoeveel rust een speler minimaal krijgt, of er met
 * poules gewerkt wordt en hoeveel wedstrijden een speler per dag mag spelen.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class CompetitionSettings implements Arrayable, Castable
{
    public const int DEFAULT_MATCH_DURATION_MINUTES = 20;

    public const int MIN_MATCH_DURATION_MINUTES = 5;

    public const int MAX_MATCH_DURATION_MINUTES = 120;

    public const int DEFAULT_BUFFER_MINUTES = 5;

    public const int MIN_BUFFER_MINUTES = 0;

    public const int MAX_BUFFER_MINUTES = 60;

    public const int DEFAULT_MIN_REST_MINUTES = 10;

    public const int MIN_MIN_REST_MINUTES = 0;

    public const int MAX_MIN_REST_MINUTES = 60;

    public const int DEFAULT_BREAK_DURATION_MINUTES = 15;

    public const int MIN_BREAK_DURATION_MINUTES = 0;

    public const int MAX_BREAK_DURATION_MINUTES = 120;

    public const int MIN_POOL_SIZE = 3;

    public const int MAX_POOL_SIZE = 8;

    public const int DEFAULT_POOL_SIZE = 4;

    public const int DEFAULT_MAX_MATCHES_PER_PLAYER_PER_DAY = 0;

    public const int MIN_MAX_MATCHES_PER_PLAYER_PER_DAY = 0;

    public const int MAX_MAX_MATCHES_PER_PLAYER_PER_DAY = 50;

    public function __construct(
        public int $matchDurationMinutes,
        public int $bufferMinutes,
        public int $minRestMinutes,
        public int $breakDurationMinutes,
        public bool $usePools,
        public ?int $poolSize,
        public int $maxMatchesPerPlayerPerDay,
    ) {}

    public static function defaults(): self
    {
        return new self(
            matchDurationMinutes: self::DEFAULT_MATCH_DURATION_MINUTES,
            bufferMinutes: self::DEFAULT_BUFFER_MINUTES,
            minRestMinutes: self::DEFAULT_MIN_REST_MINUTES,
            breakDurationMinutes: self::DEFAULT_BREAK_DURATION_MINUTES,
            usePools: false,
            poolSize: null,
            maxMatchesPerPlayerPerDay: self::DEFAULT_MAX_MATCHES_PER_PLAYER_PER_DAY,
        );
    }

    /**
     * Onbekende keys worden genegeerd zodat oudere of nieuwere payloads
     * (bv. na een toekomstige uitbreiding) niet crashen.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            matchDurationMinutes: (int) ($data['match_duration_minutes'] ?? self::DEFAULT_MATCH_DURATION_MINUTES),
            bufferMinutes: (int) ($data['buffer_minutes'] ?? self::DEFAULT_BUFFER_MINUTES),
            minRestMinutes: (int) ($data['min_rest_minutes'] ?? self::DEFAULT_MIN_REST_MINUTES),
            breakDurationMinutes: (int) ($data['break_duration_minutes'] ?? self::DEFAULT_BREAK_DURATION_MINUTES),
            usePools: (bool) ($data['use_pools'] ?? false),
            poolSize: isset($data['pool_size']) ? (int) $data['pool_size'] : null,
            maxMatchesPerPlayerPerDay: (int) ($data['max_matches_per_player_per_day'] ?? self::DEFAULT_MAX_MATCHES_PER_PLAYER_PER_DAY),
        );
    }

    /**
     * @return array{match_duration_minutes: int, buffer_minutes: int, min_rest_minutes: int, break_duration_minutes: int, use_pools: bool, pool_size: int|null, max_matches_per_player_per_day: int}
     */
    public function toArray(): array
    {
        return [
            'match_duration_minutes' => $this->matchDurationMinutes,
            'buffer_minutes' => $this->bufferMinutes,
            'min_rest_minutes' => $this->minRestMinutes,
            'break_duration_minutes' => $this->breakDurationMinutes,
            'use_pools' => $this->usePools,
            'pool_size' => $this->poolSize,
            'max_matches_per_player_per_day' => $this->maxMatchesPerPlayerPerDay,
        ];
    }

    /**
     * @return array{match_duration_minutes: array{min: int, max: int}, buffer_minutes: array{min: int, max: int}, min_rest_minutes: array{min: int, max: int}, break_duration_minutes: array{min: int, max: int}, pool_size: array{min: int, max: int, default: int}, max_matches_per_player_per_day: array{min: int, max: int}}
     */
    public static function limits(): array
    {
        return [
            'match_duration_minutes' => ['min' => self::MIN_MATCH_DURATION_MINUTES, 'max' => self::MAX_MATCH_DURATION_MINUTES],
            'buffer_minutes' => ['min' => self::MIN_BUFFER_MINUTES, 'max' => self::MAX_BUFFER_MINUTES],
            'min_rest_minutes' => ['min' => self::MIN_MIN_REST_MINUTES, 'max' => self::MAX_MIN_REST_MINUTES],
            'break_duration_minutes' => ['min' => self::MIN_BREAK_DURATION_MINUTES, 'max' => self::MAX_BREAK_DURATION_MINUTES],
            'pool_size' => ['min' => self::MIN_POOL_SIZE, 'max' => self::MAX_POOL_SIZE, 'default' => self::DEFAULT_POOL_SIZE],
            'max_matches_per_player_per_day' => ['min' => self::MIN_MAX_MATCHES_PER_PLAYER_PER_DAY, 'max' => self::MAX_MAX_MATCHES_PER_PLAYER_PER_DAY],
        ];
    }

    /**
     * @param  array<int, string>  $arguments
     * @return class-string<CompetitionSettingsCast>
     */
    public static function castUsing(array $arguments): string
    {
        return CompetitionSettingsCast::class;
    }
}
