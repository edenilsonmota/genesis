<?php

use App\Models\User;
use App\PermissionLevel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

it('lets the global administrator complete personal information and upload a profile photo', function () {
    Storage::fake('public');
    $administrator = User::factory()->globalAdministrator()->create(['display_name' => 'Administrador Genesis']);

    $this->actingAs($administrator)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Meu perfil')
        ->assertSee('Administrador Genesis');

    $this->actingAs($administrator)->patch(route('profile.details.update'), [
        'name' => 'Administrador Geral',
        'cpf' => '111.444.777-35',
        'email' => 'ADMIN@GENESIS.TEST',
        'phone' => '(11) 99876-5432',
        'profile_photo' => UploadedFile::fake()->image('perfil.png'),
    ])->assertRedirect(route('profile.edit'));

    $administrator->refresh();
    expect($administrator->display_name)->toBe('Administrador Geral')
        ->and($administrator->cpf)->toBe('11144477735')
        ->and($administrator->email)->toBe('admin@genesis.test')
        ->and($administrator->phone)->toBe('11998765432')
        ->and($administrator->profile_photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($administrator->profile_photo_path);
});

it('updates member-backed profile information in the member record', function () {
    $user = userWithPermission('dashboard', PermissionLevel::Read);
    $member = $user->member;

    $this->actingAs($user)->patch(route('profile.details.update'), [
        'name' => 'Ana Maria Souza',
        'cpf' => $member->cpf,
        'email' => 'ANA.MARIA@GENESIS.TEST',
        'phone' => '(21) 98765-4321',
    ])->assertRedirect(route('profile.edit'));

    expect($member->refresh()->name)->toBe('Ana Maria Souza')
        ->and($member->email)->toBe('ana.maria@genesis.test')
        ->and($member->phone)->toBe('21987654321')
        ->and($user->refresh()->display_name)->toBe('Ana Maria Souza');
});

it('normalizes the personal username and rejects one already used by another account', function () {
    $administrator = User::factory()->globalAdministrator()->create(['username' => 'admin.genesis']);
    User::factory()->create(['username' => 'existente']);

    $this->actingAs($administrator)->patch(route('profile.username.update'), ['username' => '  NOVO.ADMIN  '])
        ->assertRedirect(route('profile.edit'));
    expect($administrator->refresh()->username)->toBe('novo.admin');

    $this->actingAs($administrator)->from(route('profile.edit'))->patch(route('profile.username.update'), ['username' => 'EXISTENTE'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('username');
});

it('changes the current user password without storing it in plain text', function () {
    $administrator = User::factory()->globalAdministrator()->create([
        'password' => Hash::make('CurrentPassword123'),
    ]);

    $this->actingAs($administrator)->put(route('profile.password.update'), [
        'current_password' => 'CurrentPassword123',
        'password' => 'NewPermanentPassword456',
        'password_confirmation' => 'NewPermanentPassword456',
    ])->assertRedirect(route('profile.edit'));

    expect($administrator->refresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('NewPermanentPassword456', $administrator->password))->toBeTrue()
        ->and($administrator->password)->not->toBe('NewPermanentPassword456');
});
