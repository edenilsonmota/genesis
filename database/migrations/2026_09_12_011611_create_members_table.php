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
        Schema::create('members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('cpf', 11)->unique();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('sex', ['female', 'male'])->nullable();
            $table->string('postal_code', 8)->nullable();
            $table->string('street')->nullable();
            $table->string('number', 30)->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['city_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
