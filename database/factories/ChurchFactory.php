<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Church;
use App\Models\City;
use App\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Church>
 */
class ChurchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'area_id' => Area::factory(),
            'city_id' => City::factory(),
            'name' => fake()->unique()->company().' Igreja',
            'postal_code' => fake()->numerify('########'),
            'street' => fake()->streetName(),
            'neighborhood' => fake()->word(),
            'number' => fake()->buildingNumber(),
            'complement' => null,
            'status' => Status::Active,
        ];
    }
}
