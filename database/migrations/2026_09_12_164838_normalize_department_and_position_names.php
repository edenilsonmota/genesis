<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $normalizedName = "UPPER(REGEXP_REPLACE(BTRIM(name), '\\s+', ' ', 'g'))";

        $departmentDuplicates = DB::select("SELECT area_id, {$normalizedName} AS normalized_name FROM departments GROUP BY area_id, {$normalizedName} HAVING COUNT(*) > 1");
        $positionDuplicates = DB::select("SELECT area_id, COALESCE(department_id::text, '') AS department_scope, {$normalizedName} AS normalized_name FROM positions GROUP BY area_id, COALESCE(department_id::text, ''), {$normalizedName} HAVING COUNT(*) > 1");

        if ($departmentDuplicates !== [] || $positionDuplicates !== []) {
            throw new RuntimeException('Existem nomes de departamentos ou cargos que se tornam duplicados após a normalização. Corrija os registros antes de migrar.');
        }

        DB::statement("UPDATE departments SET name = {$normalizedName}");
        DB::statement("UPDATE positions SET name = {$normalizedName}");
        DB::statement('ALTER TABLE departments ADD CONSTRAINT departments_name_uppercase_check CHECK (name = UPPER(name))');
        DB::statement('ALTER TABLE positions ADD CONSTRAINT positions_name_uppercase_check CHECK (name = UPPER(name))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE positions DROP CONSTRAINT IF EXISTS positions_name_uppercase_check');
        DB::statement('ALTER TABLE departments DROP CONSTRAINT IF EXISTS departments_name_uppercase_check');
    }
};
