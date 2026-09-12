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
        Schema::create('access_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('area_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('fixed')->default(false);
            $table->boolean('is_administrator')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['area_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_roles');
    }
};
