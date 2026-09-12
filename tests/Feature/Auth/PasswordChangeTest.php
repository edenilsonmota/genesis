<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('redirects a user with a temporary password to the required change form', function () {
    $user = User::factory()->requiringPasswordChange()->create([
        'username' => 'temporary-user',
        'password' => Hash::make('Temporary123'),
    ]);

    $response = $this->post(route('login.store'), [
        'username' => 'temporary-user',
        'password' => 'Temporary123',
    ]);

    $response->assertRedirect(route('password.change.edit'));
    $this->get(route('dashboard'))->assertRedirect(route('password.change.edit'));
    $this->get(route('password.change.edit'))->assertSee('Crie uma nova senha');
});

it('changes a temporary password and releases the dashboard', function () {
    $user = User::factory()->requiringPasswordChange()->create([
        'password' => Hash::make('Temporary123'),
    ]);

    $response = $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => 'Temporary123',
        'password' => 'PermanentPassword456',
        'password_confirmation' => 'PermanentPassword456',
    ]);

    $response
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Senha alterada com sucesso.');
    expect($user->refresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('PermanentPassword456', $user->password))->toBeTrue();
    $this->get(route('dashboard'))->assertSee($user->display_name);
});

it('keeps the temporary password when the current password is incorrect', function () {
    $user = User::factory()->requiringPasswordChange()->create([
        'password' => Hash::make('Temporary123'),
    ]);

    $response = $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => 'Incorrect123',
        'password' => 'PermanentPassword456',
        'password_confirmation' => 'PermanentPassword456',
    ]);

    $response->assertSessionHasErrors('current_password');
    expect($user->refresh()->must_change_password)->toBeTrue()
        ->and(Hash::check('Temporary123', $user->password))->toBeTrue();
});
