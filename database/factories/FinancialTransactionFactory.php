<?php

namespace Database\Factories;

use App\Enums\FinancialPaymentMethod;
use App\Enums\FinancialTransactionOrigin;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialTransaction>
 */
class FinancialTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => FinancialTransactionType::Income,
            'origin' => FinancialTransactionOrigin::Manual,
            'category_id' => FinancialCategory::factory()->state(['type' => 'income']),
            'department_id' => null,
            'member_id' => null,
            'responsible_member_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'counterparty_name' => fake()->optional()->name(),
            'document_number' => null,
            'amount' => fake()->randomFloat(2, 1, 5000),
            'occurred_on' => today(),
            'competence_month' => null,
            'payment_method' => FinancialPaymentMethod::Pix,
            'status' => FinancialTransactionStatus::Pending,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function expense(): static
    {
        return $this->state(fn (): array => [
            'type' => FinancialTransactionType::Expense,
            'category_id' => FinancialCategory::factory()->state(['type' => 'expense']),
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn (): array => [
            'type' => FinancialTransactionType::Transfer,
            'category_id' => null,
            'department_id' => null,
            'member_id' => null,
            'responsible_member_id' => null,
        ]);
    }

    public function settled(): static
    {
        return $this->state(fn (): array => ['status' => FinancialTransactionStatus::Settled]);
    }
}
