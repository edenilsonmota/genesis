<?php

namespace Database\Factories;

use App\Models\PermissionModule;
use App\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermissionModule>
 */
class PermissionModuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->lexify('module_??????'),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'category' => fake()->word(),
            'status' => Status::Active,
        ];
    }
}
