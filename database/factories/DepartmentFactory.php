<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Department;
use App\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Department> */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'area_id' => fn (): string => Area::query()->value('id') ?? Area::factory()->create()->id,
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'status' => Status::Active,
        ];
    }
}
