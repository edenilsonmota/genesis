<?php

namespace Database\Factories;

use App\Enums\FinancialCategoryType;
use App\Models\Area;
use App\Models\FinancialCategory;
use App\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialCategory> */
class FinancialCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'area_id' => fn (): string => Area::query()->value('id') ?? Area::factory()->create()->id,
            'name' => fake()->unique()->words(2, true),
            'type' => fake()->randomElement(FinancialCategoryType::cases()),
            'description' => fake()->optional()->sentence(),
            'fixed' => false,
            'status' => Status::Active,
        ];
    }

    public function fixed(): static
    {
        return $this->state(fn (): array => ['fixed' => true]);
    }
}
