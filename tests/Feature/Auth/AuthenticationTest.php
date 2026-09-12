<?php

use App\Models\Area;
use App\Models\Church;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\MemberPositionAssignment;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('authenticates an active global administrator with username and password', function () {
    $user = User::factory()->globalAdministrator()->create(['username' => 'joao.silva', 'password' => Hash::make('Temporary123')]);

    $response = $this->post(route('login.store'), ['username' => '  JOAO.SILVA  ', 'password' => 'Temporary123']);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->last_login_at)->not->toBeNull();
});

it('authenticates a member only while an active cargo grants system access', function () {
    $user = User::factory()->create(['username' => 'maria.souza', 'password' => Hash::make('Temporary123')]);
    grantPermissionToUser($user, 'dashboard');

    $this->post(route('login.store'), ['username' => 'maria.souza', 'password' => 'Temporary123'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('rejects valid credentials when no current cargo grants access', function () {
    $user = User::factory()->create(['username' => 'sem.cargo', 'password' => Hash::make('Temporary123')]);

    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'Temporary123'])
        ->assertSessionHasErrors(['username' => 'Seu acesso ao sistema não está disponível. Procure um administrador.']);
    $this->assertGuest();
});

it('rejects valid credentials when the member has only an organizational cargo', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $member = Member::factory()->create();
    $membership = MemberChurchMembership::factory()->for($member)->for($church)->create();
    $position = Position::factory()->for($area)->create(['grants_system_access' => false]);
    MemberPositionAssignment::factory()->for($membership, 'membership')->for($position)->create();
    $user = User::factory()->for($member)->create(['username' => 'cargo.organizacional', 'password' => Hash::make('Temporary123')]);

    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'Temporary123'])
        ->assertSessionHasErrors('username');
    $this->assertGuest();
});

it('rejects an incorrect password without revealing which credential failed', function () {
    User::factory()->globalAdministrator()->create(['username' => 'wrong-password', 'password' => Hash::make('Temporary123')]);

    $this->from(route('login'))->post(route('login.store'), ['username' => 'wrong-password', 'password' => 'Incorrect123'])
        ->assertRedirect(route('login'))->assertSessionHasErrors(['username' => 'As credenciais informadas não são válidas.']);
    $this->assertGuest();
});

it('rejects an inactive user and member contact email', function () {
    User::factory()->globalAdministrator()->inactive()->create(['username' => 'inactive-user', 'password' => Hash::make('Temporary123')]);
    $member = Member::factory()->create(['email' => 'member@example.com']);
    User::factory()->for($member)->create(['username' => 'member-user', 'password' => Hash::make('Temporary123')]);

    $this->post(route('login.store'), ['username' => 'inactive-user', 'password' => 'Temporary123'])->assertSessionHasErrors('username');
    $this->post(route('login.store'), ['username' => 'member@example.com', 'password' => 'Temporary123'])->assertSessionHasErrors('username');
    $this->assertGuest();
});

it('rate limits repeated failed login attempts', function () {
    User::factory()->globalAdministrator()->create(['username' => 'limited-user', 'password' => Hash::make('Temporary123')]);
    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['username' => 'limited-user', 'password' => 'Incorrect123'])->assertSessionHasErrors('username');
    }

    $this->post(route('login.store'), ['username' => 'limited-user', 'password' => 'Temporary123'])->assertSessionHasErrors('username');
    expect(session('errors')->first('username'))->toContain('Muitas tentativas');
});

it('regenerates session security values on login and logout', function () {
    $user = User::factory()->globalAdministrator()->create(['password' => Hash::make('Temporary123')]);
    $this->withSession(['session_marker' => 'preserved']);
    $beforeLogin = session()->getId();

    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'Temporary123'])
        ->assertRedirect(route('dashboard'))->assertSessionHas('session_marker', 'preserved');
    expect(session()->getId())->not->toBe($beforeLogin);
    $beforeLogout = session()->getId();
    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
    expect(session()->getId())->not->toBe($beforeLogout);
});
