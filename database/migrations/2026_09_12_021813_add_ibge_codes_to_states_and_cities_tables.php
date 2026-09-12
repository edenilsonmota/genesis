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
        Schema::table('states', function (Blueprint $table) {
            $table->unsignedSmallInteger('ibge_code')->nullable()->unique();
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->unsignedInteger('ibge_code')->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropUnique(['ibge_code']);
            $table->dropColumn('ibge_code');
        });

        Schema::table('states', function (Blueprint $table) {
            $table->dropUnique(['ibge_code']);
            $table->dropColumn('ibge_code');
        });
    }
};
