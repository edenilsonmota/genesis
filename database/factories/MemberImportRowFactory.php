<?php

namespace Database\Factories;

use App\Enums\MemberImportOperation;
use App\Enums\MemberImportRowStatus;
use App\Models\MemberImport;
use App\Models\MemberImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberImportRow>
 */
class MemberImportRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_import_id' => MemberImport::factory(),
            'row_number' => fake()->unique()->numberBetween(2, 5001),
            'operation' => MemberImportOperation::Create,
            'member_id' => null,
            'normalized_payload' => [],
            'errors' => null,
            'status' => MemberImportRowStatus::Valid,
        ];
    }
}
