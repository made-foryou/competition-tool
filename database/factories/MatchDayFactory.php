<?php

namespace Database\Factories;

use App\Models\Competition;
use App\Models\MatchDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchDay>
 */
class MatchDayFactory extends Factory
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
            'date' => now()->addWeek()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ];
    }

    /**
     * Een speeldag met een reeks automatisch genummerde speelvelden.
     */
    public function withFields(int $count = 4): static
    {
        return $this->afterCreating(function (MatchDay $matchDay) use ($count): void {
            $matchDay->fields()->createMany(
                collect(range(1, $count))
                    ->map(fn (int $position): array => [
                        'name' => __('Field :number', ['number' => $position]),
                        'position' => $position,
                    ])
                    ->all(),
            );
        });
    }
}
