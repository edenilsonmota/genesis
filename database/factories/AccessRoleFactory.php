<?php

namespace Database\Factories;

use App\Models\AccessRole;
use App\Models\Area;
use App\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessRole>
 */
class AccessRoleFactory extends Factory
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
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'fixed' => false,
            'is_administrator' => false,
            'status' => Status::Active,
        ];
    }

    public function globalAdministrator(): static
    {
        return $this->state(fn (array $attributes): array => [
            'area_id' => null,
            'fixed' => true,
            'is_administrator' => true,
            'status' => Status::Active,
        ]);
    }
}
