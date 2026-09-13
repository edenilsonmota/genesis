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
        Schema::create('financial_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->enum('type', ['income', 'expense', 'transfer', 'reversal']);
            $table->enum('origin', ['manual', 'tithe', 'system'])->default('manual');
            $table->foreignUuid('category_id')->nullable()->constrained('financial_categories')->restrictOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('member_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('responsible_member_id')->nullable()->constrained('members')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('counterparty_name')->nullable();
            $table->string('document_number')->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('occurred_on');
            $table->date('competence_month')->nullable();
            $table->enum('payment_method', ['cash', 'pix', 'bank_transfer', 'debit_card', 'credit_card', 'check', 'other'])->nullable();
            $table->enum('status', ['draft', 'pending', 'settled', 'cancelled']);
            $table->foreignUuid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('cancelled_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignUuid('reversed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->uuid('reversal_of_transaction_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'occurred_on']);
            $table->index(['type', 'occurred_on']);
            $table->index(['origin', 'occurred_on']);
            $table->index(['category_id', 'occurred_on']);
            $table->index(['department_id', 'occurred_on']);
            $table->index(['responsible_member_id', 'occurred_on']);
        });

        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->foreign('reversal_of_transaction_id')
                ->references('id')
                ->on('financial_transactions')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_positive_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_competence_month_check CHECK (competence_month IS NULL OR EXTRACT(DAY FROM competence_month) = 1)");
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_transfer_classification_check CHECK (type <> 'transfer' OR (category_id IS NULL AND department_id IS NULL AND member_id IS NULL AND responsible_member_id IS NULL))");
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_cancellation_metadata_check CHECK ((status = 'cancelled' AND cancelled_by_user_id IS NOT NULL AND cancelled_at IS NOT NULL AND cancellation_reason IS NOT NULL) OR (status <> 'cancelled' AND cancelled_by_user_id IS NULL AND cancelled_at IS NULL AND cancellation_reason IS NULL))");
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_reversal_link_check CHECK ((type = 'reversal' AND reversal_of_transaction_id IS NOT NULL AND origin = 'system') OR (type <> 'reversal' AND reversal_of_transaction_id IS NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
