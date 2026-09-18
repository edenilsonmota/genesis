<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('member_import_rows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('operation', 20);
            $table->foreignUuid('member_id')->nullable()->constrained()->restrictOnDelete();
            $table->jsonb('normalized_payload')->nullable();
            $table->jsonb('errors')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['member_import_id', 'row_number']);
            $table->index(['member_import_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_import_rows');
    }
};
