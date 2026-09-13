<?php

namespace Database\Factories;

use App\Enums\MatchStatus;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitionMatch>
 */
class CompetitionMatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'first_player_id' => User::factory(),
            'second_player_id' => User::factory(),
            'status' => MatchStatus::Pending,
            'first_player_score' => null,
            'second_player_score' => null,
        ];
    }

    /**
     * Sorteert de twee user-ids zodat de factory altijd canonieke paren
     * bouwt (laagste id als first player), ook als een test de ids in
     * omgekeerde volgorde meegeeft.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (CompetitionMatch $match): void {
            if ($match->first_player_id > $match->second_player_id) {
                [$match->first_player_id, $match->second_player_id] = [$match->second_player_id, $match->first_player_id];
            }
        });
    }

    /**
     * Een wedstrijd waarvan de uitslag al geregistreerd is.
     */
    public function played(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MatchStatus::Played,
            'first_player_score' => 3,
            'second_player_score' => 1,
        ]);
    }
}
