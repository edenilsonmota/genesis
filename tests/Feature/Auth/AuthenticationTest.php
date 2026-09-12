<?php

use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('authenticates an active user with the correct username and password', function () {
    $user = User::factory()->create([
        'username' => 'joao.silva',
        'password' => Hash::make('Temporary123'),
    ]);

    $response = $this->post(route('login.store'), [
        'username' => 'joao.silva',
        'password' => 'Temporary123',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->last_login_at)->not->toBeNull();
});

it('normalizes surrounding whitespace and letter case before authentication', function () {
    $user = User::factory()->create([
        'username' => 'maria.souza',
        'password' => Hash::make('Temporary123'),
    ]);

    $response = $this->post(route('login.store'), [
        'username' => '  MARIA.SOUZA  ',
        'password' => 'Temporary123',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('rejects an incorrect password without revealing which credential failed', function () {
    User::factory()->create([
        'username' => 'wrong-password',
        'password' => Hash::make('Temporary123'),
    ]);

    $response = $this->from(route('login'))->post(route('login.store'), [
        'username' => 'wrong-password',
        'password' => 'Incorrect123',
    ]);

    $response
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['username' => 'As credenciais informadas não são válidas.']);
    $this->assertGuest();
});

it('rejects an inactive user', function () {
    User::factory()->inactive()->create([
        'username' => 'inactive-user',
        'password' => Hash::make('Temporary123'),
    ]);

    $response = $this->post(route('login.store'), [
        'username' => 'inactive-user',
        'password' => 'Temporary123',
    ]);

    $response->assertSessionHasErrors('username');
    $this->assertGuest();
});

it('does not accept a member contact email as a login identifier', function () {
    $member = Member::factory()->create(['email' => 'member@example.com']);
    User::factory()->for($member)->create([
        'username' => 'member-user',
        'password' => Hash::make('Temporary123'),
    ]);

    $response = $this->post(route('login.store'), [
        'username' => 'member@example.com',
        'password' => 'Temporary123',
    ]);

    $response->assertSessionHasErrors('username');
    $this->assertGuest();
});

it('rate limits repeated failed login attempts', function () {
    User::factory()->create([
        'username' => 'limited-user',
        'password' => Hash::make('Temporary123'),
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), [
            'username' => 'limited-user',
            'password' => 'Incorrect123',
        ])->assertSessionHasErrors('username');
    }

    $response = $this->post(route('login.store'), [
        'username' => 'limited-user',
        'password' => 'Temporary123',
    ]);

    $response->assertSessionHasErrors('username');
    expect(session('errors')->first('username'))->toContain('Muitas tentativas');
    $this->assertGuest();
});

it('regenerates the session identifier after authentication', function () {
    $user = User::factory()->create([
        'username' => 'session-user',
        'password' => Hash::make('Temporary123'),
    ]);
    $this->withSession(['session_marker' => 'preserved']);
    $previousSessionId = session()->getId();

    $response = $this->post(route('login.store'), [
        'username' => 'session-user',
        'password' => 'Temporary123',
    ]);

    $response
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('session_marker', 'preserved');
    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($previousSessionId);
});

it('logs the user out and rotates session security values', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['_token' => 'known-csrf-token']);
    $previousSessionId = session()->getId();

    $response = $this->post(route('logout'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    expect(session()->getId())
        ->not->toBe($previousSessionId)
        ->and(session()->token())->not->toBe('known-csrf-token');
});
