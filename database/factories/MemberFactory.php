<?php

namespace Database\Factories;

use App\Enums\Sex;
use App\Models\City;
use App\Models\Member;
use App\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'cpf' => fake()->unique()->numerify('###########'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'birth_date' => fake()->dateTimeBetween('-90 years', '-12 years'),
            'sex' => fake()->randomElement(Sex::cases()),
            'postal_code' => fake()->numerify('########'),
            'street' => fake()->streetName(),
            'number' => fake()->buildingNumber(),
            'complement' => null,
            'neighborhood' => fake()->word(),
            'city_id' => City::factory(),
            'status' => Status::Active,
        ];
    }
}
