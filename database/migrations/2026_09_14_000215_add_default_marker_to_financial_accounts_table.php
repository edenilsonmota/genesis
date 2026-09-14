<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('status');
        });

        $now = now();

        DB::table('areas')->orderBy('id')->each(function (object $area) use ($now): void {
            $account = DB::table('financial_accounts')
                ->where('area_id', $area->id)
                ->whereNull('church_id')
                ->orderBy('created_at')
                ->first();

            if ($account !== null) {
                DB::table('financial_accounts')->where('id', $account->id)->update(['is_default' => true]);

                return;
            }

            DB::table('financial_accounts')->insert([
                'id' => (string) Str::uuid(),
                'area_id' => $area->id,
                'church_id' => null,
                'name' => 'CAIXA DA ÁREA',
                'type' => 'cash',
                'status' => 'active',
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        DB::table('churches')->orderBy('id')->each(function (object $church) use ($now): void {
            $account = DB::table('financial_accounts')
                ->where('church_id', $church->id)
                ->whereNull('area_id')
                ->orderBy('created_at')
                ->first();

            if ($account !== null) {
                DB::table('financial_accounts')->where('id', $account->id)->update(['is_default' => true]);

                return;
            }

            DB::table('financial_accounts')->insert([
                'id' => (string) Str::uuid(),
                'area_id' => null,
                'church_id' => $church->id,
                'name' => 'CAIXA DA IGREJA',
                'type' => 'cash',
                'status' => 'active',
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        DB::statement('CREATE UNIQUE INDEX financial_accounts_area_default_unique ON financial_accounts (area_id) WHERE area_id IS NOT NULL AND church_id IS NULL AND is_default');
        DB::statement('CREATE UNIQUE INDEX financial_accounts_church_default_unique ON financial_accounts (church_id) WHERE church_id IS NOT NULL AND area_id IS NULL AND is_default');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS financial_accounts_area_default_unique');
        DB::statement('DROP INDEX IF EXISTS financial_accounts_church_default_unique');

        Schema::table('financial_accounts', function (Blueprint $table): void {
            $table->dropColumn('is_default');
        });
    }
};
