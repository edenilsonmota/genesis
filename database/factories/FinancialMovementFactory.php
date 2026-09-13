<?php

namespace Database\Factories;

use App\Enums\FinancialMovementDirection;
use App\Models\FinancialAccount;
use App\Models\FinancialMovement;
use App\Models\FinancialTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialMovement>
 */
class FinancialMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'financial_transaction_id' => FinancialTransaction::factory(),
            'financial_account_id' => FinancialAccount::factory(),
            'direction' => FinancialMovementDirection::Inflow,
            'amount' => fake()->randomFloat(2, 1, 5000),
            'settled_on' => null,
        ];
    }
}
