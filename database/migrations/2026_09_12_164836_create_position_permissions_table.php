<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('position_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('permission_module_id')->constrained()->cascadeOnDelete();
            $table->enum('level', ['read', 'write']);
            $table->timestamps();
            $table->unique(['position_id', 'permission_module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_permissions');
    }
};
