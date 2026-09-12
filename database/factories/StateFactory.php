<?php

namespace Database\Factories;

use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<State>
 */
class StateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ibge_code' => fake()->unique()->numberBetween(100, 32_000),
            'abbreviation' => fake()->unique()->regexify('[A-Z]{2}'),
            'name' => fake()->unique()->state(),
        ];
    }
}
