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
        Schema::create('user_global_access_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('access_role_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['access_role_id', 'status']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE user_global_access_roles
            ADD CONSTRAINT user_global_access_roles_dates_check
            CHECK (ended_at IS NULL OR started_at IS NULL OR ended_at >= started_at)
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX user_global_access_roles_active_unique
            ON user_global_access_roles (user_id, access_role_id)
            WHERE status = 'active' AND ended_at IS NULL
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_global_access_roles');
    }
};
