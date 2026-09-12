<?php

namespace Database\Factories;

use App\Models\MemberChurchMembership;
use App\Models\MemberPositionAssignment;
use App\Models\Position;
use App\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MemberPositionAssignment> */
class MemberPositionAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_church_membership_id' => MemberChurchMembership::factory(),
            'position_id' => Position::factory(),
            'status' => Status::Active,
            'started_at' => today(),
            'ended_at' => null,
        ];
    }
}
