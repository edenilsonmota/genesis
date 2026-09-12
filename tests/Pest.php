<?php

use App\Models\Area;
use App\Models\Church;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\MemberPositionAssignment;
use App\Models\PermissionModule;
use App\Models\Position;
use App\Models\PositionPermission;
use App\Models\User;
use App\PermissionLevel;
use App\Status;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function grantPermissionToUser(
    User $user,
    string $moduleKey,
    PermissionLevel $level = PermissionLevel::Read,
    ?Church $church = null,
): Church {
    $area = $church?->area ?? Area::query()->first() ?? Area::factory()->create();
    $church ??= Church::factory()->for($area)->create();
    $member = $user->member_id ? Member::query()->findOrFail($user->member_id) : Member::factory()->create();
    if ($user->member_id === null) {
        $user->update(['member_id' => $member->id]);
    }
    $membership = MemberChurchMembership::query()
        ->where('member_id', $member->id)
        ->where('church_id', $church->id)
        ->first() ?? MemberChurchMembership::factory()->for($member)->for($church)->create();
    $position = Position::factory()->for($area)->grantingAccess()->create();
    $module = PermissionModule::query()->firstOrCreate(
        ['key' => $moduleKey],
        ['name' => ucfirst($moduleKey), 'description' => $moduleKey, 'category' => 'Testes', 'status' => Status::Active],
    );
    PositionPermission::factory()->for($position)->for($module, 'permissionModule')->create(['level' => $level]);
    MemberPositionAssignment::factory()->for($membership, 'membership')->for($position)->create();

    return $church;
}

function userWithPermission(
    string $moduleKey,
    PermissionLevel $level = PermissionLevel::Read,
    ?Church $church = null,
): User {
    $user = User::factory()->create();
    grantPermissionToUser($user, $moduleKey, $level, $church);

    return $user->refresh();
}

/** @return array{area: Area, church: Church, member: Member, membership: MemberChurchMembership, position: Position} */
function eligibleMemberForUser(?Church $church = null): array
{
    $area = $church?->area ?? Area::query()->first() ?? Area::factory()->create();
    $church ??= Church::factory()->for($area)->create();
    $member = Member::factory()->create();
    $membership = MemberChurchMembership::factory()->for($member)->for($church)->create();
    $position = Position::factory()->for($area)->grantingAccess()->create();
    MemberPositionAssignment::factory()->for($membership, 'membership')->for($position)->create();

    return compact('area', 'church', 'member', 'membership', 'position');
}
