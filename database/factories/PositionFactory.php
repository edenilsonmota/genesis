<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Position;
use App\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Position> */
class PositionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'area_id' => fn (): string => Area::query()->value('id') ?? Area::factory()->create()->id,
            'department_id' => null,
            'name' => fake()->unique()->jobTitle(),
            'description' => fake()->sentence(),
            'grants_system_access' => false,
            'fixed' => false,
            'status' => Status::Active,
        ];
    }

    public function grantingAccess(): static
    {
        return $this->state(fn (): array => ['grants_system_access' => true]);
    }

    public function fixed(): static
    {
        return $this->state(fn (): array => ['fixed' => true]);
    }
}
