<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('redirects an eligible user with a temporary password to the required change form', function () {
    $user = User::factory()->globalAdministrator()->requiringPasswordChange()->create(['password' => Hash::make('Temporary123')]);

    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'Temporary123'])
        ->assertRedirect(route('password.change.edit'));
    $this->get(route('dashboard'))->assertRedirect(route('password.change.edit'));
    $this->get(route('password.change.edit'))->assertSee('Crie uma nova senha');
});

it('changes a temporary password and records a sanitized audit event', function () {
    $user = User::factory()->globalAdministrator()->requiringPasswordChange()->create(['password' => Hash::make('Temporary123')]);

    $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => 'Temporary123',
        'password' => 'PermanentPassword456',
        'password_confirmation' => 'PermanentPassword456',
    ])->assertRedirect(route('dashboard'));

    expect($user->refresh()->must_change_password)->toBeFalse()->and(Hash::check('PermanentPassword456', $user->password))->toBeTrue();
    expect(AuditLog::query()->where('action', 'user.password_changed')->sole()->details)->not->toHaveKeys(['password', 'current_password']);
});

it('keeps the temporary password when the current password is incorrect', function () {
    $user = User::factory()->globalAdministrator()->requiringPasswordChange()->create(['password' => Hash::make('Temporary123')]);

    $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => 'Incorrect123', 'password' => 'PermanentPassword456', 'password_confirmation' => 'PermanentPassword456',
    ])->assertSessionHasErrors('current_password');

    expect($user->refresh()->must_change_password)->toBeTrue()->and(Hash::check('Temporary123', $user->password))->toBeTrue();
});
