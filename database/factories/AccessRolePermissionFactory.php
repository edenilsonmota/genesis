<?php

namespace Database\Factories;

use App\Models\AccessRole;
use App\Models\AccessRolePermission;
use App\Models\PermissionModule;
use App\PermissionLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessRolePermission>
 */
class AccessRolePermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'access_role_id' => AccessRole::factory(),
            'permission_module_id' => PermissionModule::factory(),
            'level' => PermissionLevel::Read,
        ];
    }
}
