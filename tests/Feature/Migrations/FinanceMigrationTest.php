<?php

use App\Models\Area;
use App\Models\Church;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('creates financial tables with ownership lifecycle and movement constraints', function () {
    expect(Schema::hasColumns('financial_accounts', [
        'id', 'area_id', 'church_id', 'name', 'type', 'institution', 'description', 'status', 'is_default', 'created_at', 'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('financial_accounts', 'balance'))->toBeFalse()
        ->and(Schema::hasColumn('financial_accounts', 'opening_balance'))->toBeFalse()
        ->and(Schema::hasColumns('financial_categories', [
            'id', 'area_id', 'name', 'type', 'description', 'fixed', 'status', 'created_at', 'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('financial_transactions', [
            'id', 'type', 'origin', 'category_id', 'department_id', 'member_id', 'responsible_member_id',
            'title', 'description', 'counterparty_name', 'document_number', 'amount', 'occurred_on',
            'competence_month', 'payment_method', 'status', 'created_by_user_id', 'updated_by_user_id',
            'cancelled_by_user_id', 'cancelled_at', 'cancellation_reason', 'reversed_at',
            'reversed_by_user_id', 'reversal_reason', 'reversal_of_transaction_id', 'created_at', 'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('financial_movements', [
            'id', 'financial_transaction_id', 'financial_account_id', 'direction', 'amount',
            'settled_on', 'created_at', 'updated_at',
        ]))->toBeTrue()
        ->and(DB::table('pg_constraint')->where('conname', 'financial_accounts_exactly_one_owner_check')->exists())->toBeTrue()
        ->and(DB::table('pg_constraint')->whereIn('conname', [
            'financial_transactions_positive_amount_check',
            'financial_transactions_competence_month_check',
            'financial_movements_positive_amount_check',
        ])->count())->toBe(3)
        ->and(DB::table('pg_indexes')->whereIn('indexname', [
            'financial_accounts_area_name_lower_unique',
            'financial_accounts_church_name_lower_unique',
            'financial_accounts_area_default_unique',
            'financial_accounts_church_default_unique',
            'financial_categories_area_type_name_lower_unique',
            'financial_movements_financial_transaction_id_financial_account_id_unique',
        ])->count())->toBe(6);
});

it('enforces exactly one financial account owner in PostgreSQL', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $attributes = [
        'id' => (string) Str::uuid(),
        'name' => 'Conta inválida',
        'type' => 'cash',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    expect(fn () => DB::transaction(fn () => DB::table('financial_accounts')->insert($attributes + [
        'area_id' => $area->id,
        'church_id' => $church->id,
    ])))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn () => DB::table('financial_accounts')->insert([
        ...$attributes,
        'id' => (string) Str::uuid(),
        'area_id' => null,
        'church_id' => null,
    ])))->toThrow(QueryException::class);
});

it('rolls back and reapplies the financial migrations', function () {
    $files = [
        '2026_09_12_230820_create_financial_accounts_table.php',
        '2026_09_12_230821_create_financial_categories_table.php',
        '2026_09_13_000120_create_financial_transactions_table.php',
        '2026_09_13_000121_create_financial_movements_table.php',
        '2026_09_14_000215_add_default_marker_to_financial_accounts_table.php',
        '2026_09_14_002304_seed_default_financial_categories_for_existing_areas.php',
    ];
    $migrations = collect($files)->map(fn (string $file) => require database_path('migrations/'.$file));

    $migrations->reverse()->each->down();
    expect(Schema::hasTable('financial_accounts'))->toBeFalse()
        ->and(Schema::hasTable('financial_categories'))->toBeFalse()
        ->and(Schema::hasTable('financial_transactions'))->toBeFalse()
        ->and(Schema::hasTable('financial_movements'))->toBeFalse();
    $migrations->each->up();

    expect(Schema::hasTable('financial_accounts'))->toBeTrue()
        ->and(Schema::hasTable('financial_categories'))->toBeTrue()
        ->and(Schema::hasTable('financial_transactions'))->toBeTrue()
        ->and(Schema::hasTable('financial_movements'))->toBeTrue();
});
