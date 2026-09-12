<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('area_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('grants_system_access')->default(false);
            $table->boolean('fixed')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->index(['area_id', 'status']);
            $table->index(['department_id', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX positions_scope_name_lower_unique ON positions (area_id, COALESCE(department_id::text, ''), LOWER(name))");
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
