<?php

use App\Models\AuditLog;
use App\Models\PermissionModule;
use App\Models\User;
use Database\Seeders\GenesisAdminSeeder;
use Illuminate\Support\Facades\Hash;

it('creates the guarded global administrator and modules idempotently', function () {
    config()->set('genesis.admin', ['name' => 'Genesis Administrator', 'username' => '  GENESIS.ADMIN  ', 'password' => 'InitialPassword123']);

    $this->seed(GenesisAdminSeeder::class);
    $this->seed(GenesisAdminSeeder::class);
    $user = User::query()->sole();

    expect($user->username)->toBe('genesis.admin')->and($user->member_id)->toBeNull()->and($user->must_change_password)->toBeTrue()
        ->and($user->is_global_administrator)->toBeTrue()->and($user->isGlobalAdministrator())->toBeTrue()
        ->and(PermissionModule::query()->count())->toBe(8)
        ->and(PermissionModule::query()->pluck('category', 'key')->all())->toMatchArray([
            'dashboard' => 'Principal', 'members' => 'Cadastros', 'users' => 'Administração',
            'positions' => 'Administração', 'areas' => 'Cadastros', 'churches' => 'Cadastros',
            'departments' => 'Administração', 'audit' => 'Administração',
        ])
        ->and(AuditLog::query()->where('action', 'global_administrator.created')->count())->toBe(1);
});

it('stores the configured administrator password only as a hash', function () {
    config()->set('genesis.admin', ['name' => 'Genesis Administrator', 'username' => 'genesis.admin', 'password' => 'InitialPassword123']);
    $this->seed(GenesisAdminSeeder::class);

    $storedPassword = User::query()->sole()->password;
    expect($storedPassword)->not->toBe('InitialPassword123')->and(Hash::check('InitialPassword123', $storedPassword))->toBeTrue();
});

it('rejects invalid administrator configuration before writing data', function () {
    config()->set('genesis.admin', ['name' => null, 'username' => null, 'password' => null]);

    expect(fn () => $this->seed(GenesisAdminSeeder::class))->toThrow(LogicException::class);
    expect(User::query()->count())->toBe(0)->and(PermissionModule::query()->count())->toBe(0);
});
