<?php

namespace Database\Seeders;

use App\Models\AccessRole;
use App\Models\AccessRolePermission;
use App\Models\PermissionModule;
use App\Models\User;
use App\Models\UserGlobalAccessRole;
use App\PermissionLevel;
use App\Status;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use LogicException;

class GenesisAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configuration = [
            'name' => config('genesis.admin.name'),
            'username' => User::normalizeUsername((string) config('genesis.admin.username')),
            'password' => config('genesis.admin.password'),
        ];

        $validator = Validator::make($configuration, [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/\A[a-z0-9._-]+\z/',
                Rule::notIn(['root', 'support', 'system']),
            ],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            throw new LogicException('Configuração do administrador Genesis inválida: '.$validator->errors()->first());
        }

        /** @var array{name: string, username: string, password: string} $admin */
        $admin = $validator->validated();

        DB::transaction(function () use ($admin): void {
            $this->call(PermissionModuleSeeder::class);

            $role = AccessRole::query()
                ->whereNull('area_id')
                ->where('name', 'Administrador global')
                ->lockForUpdate()
                ->firstOrNew();

            $role->fill([
                'area_id' => null,
                'name' => 'Administrador global',
                'description' => 'Administração técnica de todo o sistema Genesis.',
                'fixed' => true,
                'is_administrator' => true,
                'status' => Status::Active,
            ])->save();

            PermissionModule::query()->each(function (PermissionModule $module) use ($role): void {
                AccessRolePermission::query()->updateOrCreate(
                    [
                        'access_role_id' => $role->id,
                        'permission_module_id' => $module->id,
                    ],
                    ['level' => PermissionLevel::Write],
                );
            });

            $user = User::query()
                ->whereRaw('LOWER(username) = ?', [$admin['username']])
                ->lockForUpdate()
                ->first();

            if ($user?->member_id !== null) {
                throw new LogicException('O username configurado já pertence a um usuário local.');
            }

            if ($user === null) {
                $user = User::query()->create([
                    'member_id' => null,
                    'display_name' => $admin['name'],
                    'username' => $admin['username'],
                    'password' => Hash::make($admin['password']),
                    'status' => Status::Active,
                    'must_change_password' => true,
                ]);
            }

            UserGlobalAccessRole::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'access_role_id' => $role->id,
                    'status' => Status::Active,
                    'ended_at' => null,
                ],
                ['started_at' => today()],
            );
        });
    }
}
