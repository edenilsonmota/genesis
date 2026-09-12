<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\User;
use App\Status;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use LogicException;

class GenesisAdminSeeder extends Seeder
{
    public function run(): void
    {
        $configuration = [
            'name' => config('genesis.admin.name'),
            'username' => User::normalizeUsername((string) config('genesis.admin.username')),
            'password' => config('genesis.admin.password'),
        ];

        $validator = Validator::make($configuration, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/\A[a-z0-9._-]+\z/', Rule::notIn(['root', 'support', 'system'])],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            throw new LogicException('Configuração do administrador Genesis inválida: '.$validator->errors()->first());
        }

        /** @var array{name: string, username: string, password: string} $admin */
        $admin = $validator->validated();

        DB::transaction(function () use ($admin): void {
            $this->call(PermissionModuleSeeder::class);
            $user = User::query()->whereRaw('LOWER(username) = ?', [$admin['username']])->lockForUpdate()->first();

            if ($user?->member_id !== null) {
                throw new LogicException('O username configurado já pertence a um usuário local.');
            }

            if ($user === null) {
                $user = new User;
                $user->forceFill([
                    'member_id' => null,
                    'display_name' => $admin['name'],
                    'username' => $admin['username'],
                    'password' => Hash::make($admin['password']),
                    'status' => Status::Active,
                    'is_global_administrator' => true,
                    'must_change_password' => true,
                ])->save();

                AuditLog::query()->create([
                    'action' => 'global_administrator.created',
                    'resource' => 'users',
                    'record_id' => $user->id,
                    'scope_type' => 'global',
                    'details' => ['username' => $user->username],
                ]);

                return;
            }

            $changed = ! $user->is_global_administrator || $user->status !== Status::Active || $user->display_name !== $admin['name'];
            $user->forceFill([
                'display_name' => $admin['name'],
                'status' => Status::Active,
                'is_global_administrator' => true,
            ])->save();

            if ($changed) {
                AuditLog::query()->create([
                    'action' => 'global_administrator.restored',
                    'resource' => 'users',
                    'record_id' => $user->id,
                    'scope_type' => 'global',
                    'details' => ['username' => $user->username],
                ]);
            }
        });
    }
}
