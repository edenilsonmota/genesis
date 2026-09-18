<?php

namespace Database\Factories;

use App\Enums\MemberImportStatus;
use App\Models\Church;
use App\Models\MemberImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberImport>
 */
class MemberImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'uploaded_by_user_id' => User::factory(),
            'confirmed_by_user_id' => null,
            'original_filename' => 'membros.xlsx',
            'stored_filename' => fake()->uuid().'.xlsx',
            'file_path' => 'member-imports/'.fake()->uuid().'/membros.xlsx',
            'file_hash' => hash('sha256', fake()->uuid()),
            'status' => MemberImportStatus::Uploaded,
        ];
    }
}
