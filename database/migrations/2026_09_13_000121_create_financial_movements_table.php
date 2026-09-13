<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('financial_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('financial_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('financial_account_id')->constrained()->restrictOnDelete();
            $table->enum('direction', ['inflow', 'outflow']);
            $table->decimal('amount', 14, 2);
            $table->date('settled_on')->nullable();
            $table->timestamps();

            $table->unique(['financial_transaction_id', 'financial_account_id']);
            $table->index(['financial_account_id', 'settled_on']);
            $table->index(['financial_transaction_id', 'direction']);
        });

        DB::statement('ALTER TABLE financial_movements ADD CONSTRAINT financial_movements_positive_amount_check CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_movements');
    }
};
