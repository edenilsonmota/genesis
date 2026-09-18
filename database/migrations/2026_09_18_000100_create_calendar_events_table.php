<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('area_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('church_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('responsible_member_id')->nullable()->constrained('members')->restrictOnDelete();
            $table->foreignUuid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->string('type', 40);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->string('visibility', 20)->default('church');
            $table->string('status', 20)->default('confirmed');
            $table->boolean('creator_can_edit')->default(true);
            $table->boolean('responsible_can_edit')->default(true);
            $table->timestamps();

            $table->index(['area_id', 'starts_at', 'ends_at']);
            $table->index(['church_id', 'starts_at', 'ends_at']);
            $table->index(['department_id', 'visibility']);
            $table->index(['status', 'starts_at']);
        });

        DB::statement('ALTER TABLE calendar_events ADD CONSTRAINT calendar_events_period_check CHECK (ends_at > starts_at)');
        DB::statement("ALTER TABLE calendar_events ADD CONSTRAINT calendar_events_department_visibility_check CHECK (visibility <> 'department' OR department_id IS NOT NULL)");

    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
