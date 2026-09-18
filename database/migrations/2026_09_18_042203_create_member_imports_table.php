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
        Schema::create('member_imports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('church_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('confirmed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('file_path');
            $table->string('file_hash', 64);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('new_members_count')->default(0);
            $table->unsignedInteger('updates_count')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->string('status', 40);
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('file_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_imports');
    }
};
