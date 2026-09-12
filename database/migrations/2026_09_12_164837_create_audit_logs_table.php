<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('actor_user_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_username', 50)->nullable();
            $table->string('action', 80);
            $table->string('resource', 80);
            $table->string('route')->nullable();
            $table->string('record_id')->nullable();
            $table->string('scope_type', 20)->nullable();
            $table->uuid('scope_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->jsonb('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['resource', 'record_id']);
            $table->index(['scope_type', 'scope_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
