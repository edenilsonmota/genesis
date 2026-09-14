<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('cpf', 11)->nullable()->after('display_name');
            $table->string('email')->nullable()->after('cpf');
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('profile_photo_path')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['cpf', 'email', 'phone', 'profile_photo_path']);
        });
    }
};
