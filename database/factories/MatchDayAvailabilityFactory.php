<?php

namespace Database\Factories;

use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchDayAvailability>
 */
class MatchDayAvailabilityFactory extends Factory
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
            'user_id' => User::factory(),
        ];
    }
}
