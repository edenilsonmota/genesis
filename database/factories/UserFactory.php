<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Church;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\MemberPositionAssignment;
use App\Models\PermissionModule;
use App\Models\Position;
use App\Models\PositionPermission;
use App\Models\User;
use App\PermissionLevel;
use App\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'member_id' => null,
            'display_name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => Status::Active,
            'is_global_administrator' => false,
            'must_change_password' => false,
            'last_login_at' => null,
            'remember_token' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => Status::Inactive]);
    }

    public function requiringPasswordChange(): static
    {
        return $this->state(fn (): array => ['must_change_password' => true]);
    }

    public function globalAdministrator(): static
    {
        return $this->state(fn (): array => [
            'member_id' => null,
            'is_global_administrator' => true,
        ]);
    }

    public function withPositionPermission(string $moduleKey, PermissionLevel $level, ?Church $church = null): static
    {
        return $this->afterCreating(function (User $user) use ($moduleKey, $level, $church): void {
            $area = $church?->area ?? Area::query()->first() ?? Area::factory()->create();
            $church ??= Church::factory()->for($area)->create();
            $member = $user->member ?? Member::factory()->create();

            if ($user->member_id === null) {
                $user->update(['member_id' => $member->id, 'display_name' => $member->name]);
            }

            $membership = MemberChurchMembership::factory()
                ->for($member)
                ->for($church)
                ->create(['is_primary' => true]);
            $position = Position::factory()->for($area)->grantingAccess()->create();
            $module = PermissionModule::factory()->create(['key' => $moduleKey]);

            PositionPermission::factory()->for($position)->for($module, 'permissionModule')->create(['level' => $level]);
            MemberPositionAssignment::factory()->for($membership, 'membership')->for($position)->create();
        });
    }
}
