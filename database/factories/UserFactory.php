<?php

namespace Database\Factories;

use App\Models\AccessRole;
use App\Models\User;
use App\Models\UserGlobalAccessRole;
use App\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => null,
            'display_name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => Status::Active,
            'must_change_password' => false,
            'last_login_at' => null,
            'remember_token' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Status::Inactive,
        ]);
    }

    public function requiringPasswordChange(): static
    {
        return $this->state(fn (array $attributes): array => [
            'must_change_password' => true,
        ]);
    }

    public function globalAdministrator(): static
    {
        return $this->afterCreating(function (User $user): void {
            $role = AccessRole::factory()->globalAdministrator()->create([
                'name' => 'Administrador global',
            ]);

            UserGlobalAccessRole::factory()
                ->for($user)
                ->for($role, 'accessRole')
                ->create();
        });
    }
}
