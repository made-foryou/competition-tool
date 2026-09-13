<?php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<CompetitionSettings, CompetitionSettings|array<string, mixed>>
 */
final class CompetitionSettingsCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): CompetitionSettings
    {
        if ($value === null) {
            return CompetitionSettings::defaults();
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? CompetitionSettings::fromArray($decoded) : CompetitionSettings::defaults();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof CompetitionSettings) {
            return (string) json_encode($value->toArray());
        }

        if (is_array($value)) {
            return (string) json_encode(CompetitionSettings::fromArray($value)->toArray());
        }

        throw new InvalidArgumentException('The settings attribute must be a '.CompetitionSettings::class.' instance or an array.');
    }
}
