<?php

use App\Models\Member;
use App\Models\User;
use App\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

it('stores usernames in their normalized form', function () {
    $user = User::factory()->create(['username' => '  Normalized.User  ']);

    expect($user->username)->toBe('normalized.user');
});

it('enforces case insensitive username uniqueness in PostgreSQL', function () {
    User::factory()->create(['username' => 'unique.user']);

    expect(fn () => DB::table('users')->insert([
        'id' => (string) Str::uuid(),
        'member_id' => null,
        'display_name' => 'Duplicate User',
        'username' => 'UNIQUE.USER',
        'password' => Hash::make('Temporary123'),
        'status' => Status::Active->value,
        'must_change_password' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('allows a member to exist without a user account', function () {
    $member = Member::factory()->create();

    expect($member->user)->toBeNull();
});

it('allows at most one user account for each member', function () {
    $member = Member::factory()->create();
    User::factory()->for($member)->create();

    expect(fn () => User::factory()->for($member)->create())
        ->toThrow(QueryException::class);
});
