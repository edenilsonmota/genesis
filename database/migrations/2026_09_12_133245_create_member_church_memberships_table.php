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
        Schema::create('member_church_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('church_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['active', 'inactive', 'transferred'])->default('active');
            $table->boolean('is_primary')->default(false);
            $table->date('joined_at');
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index(['church_id', 'status']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE member_church_memberships
            ADD CONSTRAINT member_church_memberships_dates_check
            CHECK (ended_at IS NULL OR ended_at >= joined_at)
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX member_church_memberships_active_church_unique
            ON member_church_memberships (member_id, church_id)
            WHERE status = 'active' AND ended_at IS NULL
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX member_church_memberships_active_primary_unique
            ON member_church_memberships (member_id)
            WHERE status = 'active' AND ended_at IS NULL AND is_primary = true
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_church_memberships');
    }
};
