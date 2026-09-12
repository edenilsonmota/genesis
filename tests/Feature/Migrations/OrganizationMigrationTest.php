<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('rolls back and reapplies the organization migrations', function () {
    $singletonMigration = require database_path('migrations/2026_09_12_015932_add_singleton_constraint_to_areas_table.php');
    $churchMigration = require database_path('migrations/2026_09_12_015934_create_churches_table.php');

    $churchMigration->down();
    $singletonMigration->down();

    expect(Schema::hasTable('churches'))->toBeFalse()
        ->and(DB::table('pg_indexes')->where('indexname', 'areas_singleton_unique')->exists())->toBeFalse();

    $singletonMigration->up();
    $churchMigration->up();

    expect(Schema::hasTable('churches'))->toBeTrue()
        ->and(DB::table('pg_indexes')->where('indexname', 'areas_singleton_unique')->exists())->toBeTrue();
});
