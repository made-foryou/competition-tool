<?php

namespace Database\Factories;

use App\Models\MatchDay;
use App\Models\MatchDayField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchDayField>
 */
class MatchDayFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_day_id' => MatchDay::factory(),
            'name' => 'Veld '.fake()->unique()->numberBetween(1, 999),
            'position' => 1,
        ];
    }
}
