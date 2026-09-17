<?php

namespace Database\Factories;

use App\Enums\MatchStatus;
use App\Enums\SchedulingFailure;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\MatchDay;
use App\Models\MatchDayField;
use App\Models\User;
use App\Support\CompetitionSettings;
use Carbon\CarbonImmutable;
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

    /**
     * Een wedstrijd die volledig is ingepland op de gegeven speeldag en
     * tafel. De eindtijd wordt afgeleid van de begintijd en de
     * wedstrijdduur, net zoals de planner dat doet.
     */
    public function scheduled(MatchDay $matchDay, MatchDayField $field, string $startsAt, int $durationMinutes = CompetitionSettings::DEFAULT_MATCH_DURATION_MINUTES): static
    {
        return $this->state(fn (array $attributes) => [
            'match_day_id' => $matchDay->id,
            'match_day_field_id' => $field->id,
            'starts_at' => $startsAt,
            'ends_at' => CarbonImmutable::createFromFormat('H:i', $startsAt)->addMinutes($durationMinutes)->format('H:i'),
            'scheduling_failure' => null,
        ]);
    }

    /**
     * Een handmatig vastgezette wedstrijd; herplannen laat deze staan.
     */
    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'pinned_at' => now(),
        ]);
    }

    /**
     * Een wedstrijd die de planner niet kon plaatsen: geen speeldag, tafel of
     * tijden, met een reden.
     */
    public function unschedulable(SchedulingFailure $reason = SchedulingFailure::NoSharedMatchDay): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduling_failure' => $reason,
            'match_day_id' => null,
            'match_day_field_id' => null,
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }
}
