<?php

namespace Database\Factories;

use App\Enums\FinancialAccountType;
use App\Models\Area;
use App\Models\Church;
use App\Models\FinancialAccount;
use App\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialAccount> */
class FinancialAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'area_id' => fn (): string => Area::query()->value('id') ?? Area::factory()->create()->id,
            'church_id' => null,
            'name' => fake()->unique()->words(3, true),
            'type' => fake()->randomElement(FinancialAccountType::cases()),
            'institution' => fake()->optional()->company(),
            'description' => fake()->optional()->sentence(),
            'status' => Status::Active,
        ];
    }

    public function forChurch(Church $church): static
    {
        return $this->state(fn (): array => [
            'area_id' => null,
            'church_id' => $church->id,
        ]);
    }
}
