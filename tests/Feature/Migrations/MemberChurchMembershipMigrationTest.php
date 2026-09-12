<?php

use App\Models\Area;
use App\Models\Church;
use App\Models\City;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\State;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('has the expected membership columns indexes and constraints', function () {
    $columns = collect(DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'member_church_memberships'"))
        ->pluck('column_name');
    $indexes = collect(DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'member_church_memberships'"))
        ->pluck('indexname');

    expect($columns)->toContain('member_id', 'church_id', 'status', 'is_primary', 'joined_at', 'ended_at')
        ->and($indexes)->toContain(
            'member_church_memberships_active_church_unique',
            'member_church_memberships_active_primary_unique',
        );
});

it('blocks two active primary memberships for the same member in PostgreSQL', function () {
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    [$firstChurch, $secondChurch] = Church::factory()->count(2)->for($area)->for($city)->create();
    $member = Member::factory()->for($city)->create();
    MemberChurchMembership::factory()->for($member)->for($firstChurch)->create(['is_primary' => true]);

    expect(fn () => MemberChurchMembership::factory()->for($member)->for($secondChurch)->create(['is_primary' => true]))
        ->toThrow(QueryException::class);
});

it('blocks duplicate active memberships with the same church in PostgreSQL', function () {
    $member = Member::factory()->create();
    $church = Church::factory()->create();
    MemberChurchMembership::factory()->for($member)->for($church)->create();

    expect(fn () => MemberChurchMembership::factory()->for($member)->for($church)->create())
        ->toThrow(QueryException::class);
});

it('blocks invalid membership date ranges in PostgreSQL', function () {
    expect(fn () => MemberChurchMembership::factory()->create([
        'joined_at' => today(),
        'ended_at' => today()->subDay(),
        'status' => 'inactive',
    ]))->toThrow(QueryException::class);
});
