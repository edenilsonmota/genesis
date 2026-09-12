<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('area_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->index(['area_id', 'status']);
        });

        DB::statement('CREATE UNIQUE INDEX departments_area_name_lower_unique ON departments (area_id, LOWER(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
