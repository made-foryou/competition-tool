<?php

namespace Database\Factories;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Competition>
 */
class CompetitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = implode(' ', [
            fake()->unique()->word(),
            fake()->word(),
            fake()->word(),
        ]);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'location' => fake()->city(),
            'starts_at' => now()->addWeek()->toDateString(),
            'ends_at' => now()->addWeek()->addDay()->toDateString(),
            'status' => CompetitionStatus::Active,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => CompetitionStatus::Draft]);
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => ['status' => CompetitionStatus::Finished]);
    }
}
