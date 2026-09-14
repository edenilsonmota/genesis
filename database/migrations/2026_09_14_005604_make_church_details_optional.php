<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE churches ALTER COLUMN city_id DROP NOT NULL');
        DB::statement('ALTER TABLE churches ALTER COLUMN postal_code DROP NOT NULL');
        DB::statement('ALTER TABLE churches ALTER COLUMN street DROP NOT NULL');
        DB::statement('ALTER TABLE churches ALTER COLUMN neighborhood DROP NOT NULL');
        DB::statement('ALTER TABLE churches ALTER COLUMN number DROP NOT NULL');
    }

    public function down(): void
    {
        // Dados de endereço agora podem ser desconhecidos; não é seguro torná-los obrigatórios novamente.
    }
};
