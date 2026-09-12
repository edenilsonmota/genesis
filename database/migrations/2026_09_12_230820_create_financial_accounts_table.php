<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('area_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('church_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->enum('type', ['cash', 'checking', 'savings', 'digital_wallet', 'other']);
            $table->string('institution')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->index(['area_id', 'status']);
            $table->index(['church_id', 'status']);
            $table->index(['type', 'status']);
        });

        DB::statement('ALTER TABLE financial_accounts ADD CONSTRAINT financial_accounts_exactly_one_owner_check CHECK ((area_id IS NOT NULL AND church_id IS NULL) OR (area_id IS NULL AND church_id IS NOT NULL))');
        DB::statement('CREATE UNIQUE INDEX financial_accounts_area_name_lower_unique ON financial_accounts (area_id, LOWER(name)) WHERE area_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX financial_accounts_church_name_lower_unique ON financial_accounts (church_id, LOWER(name)) WHERE church_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};
