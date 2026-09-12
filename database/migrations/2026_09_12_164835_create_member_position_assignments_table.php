<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_position_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_church_membership_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('position_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->timestamps();
            $table->index(['member_church_membership_id', 'status']);
            $table->index(['position_id', 'status']);
        });

        DB::statement('ALTER TABLE member_position_assignments ADD CONSTRAINT member_position_assignments_dates_check CHECK (ended_at IS NULL OR ended_at >= started_at)');
        DB::statement("CREATE UNIQUE INDEX member_position_assignments_active_unique ON member_position_assignments (member_church_membership_id, position_id) WHERE status = 'active' AND ended_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('member_position_assignments');
    }
};
