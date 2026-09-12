<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberChurchMembership>
 */
class MemberChurchMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'church_id' => Church::factory(),
            'status' => Status::Active,
            'is_primary' => false,
            'joined_at' => today(),
            'ended_at' => null,
        ];
    }
}
