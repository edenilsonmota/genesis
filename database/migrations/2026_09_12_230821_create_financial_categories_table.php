<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('area_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->enum('type', ['income', 'expense']);
            $table->text('description')->nullable();
            $table->boolean('fixed')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->index(['area_id', 'type', 'status']);
        });

        DB::statement('CREATE UNIQUE INDEX financial_categories_area_type_name_lower_unique ON financial_categories (area_id, type, LOWER(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_categories');
    }
};
