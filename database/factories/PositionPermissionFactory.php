<?php

namespace Database\Factories;

use App\Models\PermissionModule;
use App\Models\Position;
use App\Models\PositionPermission;
use App\PermissionLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PositionPermission> */
class PositionPermissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'position_id' => Position::factory(),
            'permission_module_id' => PermissionModule::factory(),
            'level' => PermissionLevel::Read,
        ];
    }
}
