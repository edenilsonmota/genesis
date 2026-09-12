<?php

namespace Database\Factories;

use App\Models\AccessRole;
use App\Models\User;
use App\Models\UserGlobalAccessRole;
use App\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserGlobalAccessRole>
 */
class UserGlobalAccessRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'access_role_id' => AccessRole::factory()->globalAdministrator(),
            'status' => Status::Active,
            'started_at' => today(),
            'ended_at' => null,
        ];
    }
}
