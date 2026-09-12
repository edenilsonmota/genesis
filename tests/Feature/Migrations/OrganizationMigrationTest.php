<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('rolls back and reapplies the complete domain migrations in dependency order', function () {
    $files = [
        '2026_09_12_015932_add_singleton_constraint_to_areas_table.php',
        '2026_09_12_015934_create_churches_table.php',
        '2026_09_12_133245_create_member_church_memberships_table.php',
        '2026_09_12_164833_create_departments_table.php',
        '2026_09_12_164834_create_positions_table.php',
        '2026_09_12_164835_create_member_position_assignments_table.php',
        '2026_09_12_164836_create_position_permissions_table.php',
        '2026_09_12_164837_create_audit_logs_table.php',
        '2026_09_12_164838_normalize_department_and_position_names.php',
    ];
    $migrations = collect($files)->map(fn (string $file) => require database_path('migrations/'.$file));

    $migrations->reverse()->each->down();
    expect(Schema::hasTable('churches'))->toBeFalse()->and(DB::table('pg_indexes')->where('indexname', 'areas_singleton_unique')->exists())->toBeFalse();
    $migrations->each->up();

    expect(Schema::hasTable('churches'))->toBeTrue()
        ->and(Schema::hasTable('member_church_memberships'))->toBeTrue()
        ->and(Schema::hasTable('departments'))->toBeTrue()
        ->and(Schema::hasTable('positions'))->toBeTrue()
        ->and(Schema::hasTable('member_position_assignments'))->toBeTrue()
        ->and(Schema::hasTable('position_permissions'))->toBeTrue()
        ->and(Schema::hasTable('audit_logs'))->toBeTrue()
        ->and(DB::table('pg_constraint')->whereIn('conname', ['departments_name_uppercase_check', 'positions_name_uppercase_check'])->count())->toBe(2)
        ->and(DB::table('pg_indexes')->where('indexname', 'areas_singleton_unique')->exists())->toBeTrue();
});
