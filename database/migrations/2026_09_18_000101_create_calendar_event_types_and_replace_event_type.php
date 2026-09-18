<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var array<string, array{name: string, color: string}> */
    private const DEFAULT_TYPES = [
        'special_service' => ['name' => 'Culto especial', 'color' => '#0051F5'],
        'crusade' => ['name' => 'Cruzada', 'color' => '#16A34A'],
        'celebration' => ['name' => 'Festa', 'color' => '#2BD9FB'],
        'congress' => ['name' => 'Congresso', 'color' => '#7C3AED'],
        'meeting' => ['name' => 'Reunião', 'color' => '#475569'],
        'social_action' => ['name' => 'Ação social', 'color' => '#0D9488'],
        'vigil' => ['name' => 'Vigília', 'color' => '#4338CA'],
        'retreat' => ['name' => 'Retiro', 'color' => '#F59E0B'],
        'other' => ['name' => 'Outro', 'color' => '#64748B'],
    ];

    public function up(): void
    {
        Schema::create('calendar_event_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('area_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->string('color', 7)->default('#0051F5');
            $table->timestamps();

            $table->index('area_id');
        });
        DB::statement('CREATE UNIQUE INDEX calendar_event_types_area_name_unique ON calendar_event_types (area_id, LOWER(name))');

        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->uuid('calendar_event_type_id')->nullable();
        });

        foreach (DB::table('areas')->orderBy('id')->get(['id']) as $area) {
            $ids = [];
            foreach (self::DEFAULT_TYPES as $legacyType => $type) {
                $id = (string) Str::uuid();
                $ids[$legacyType] = $id;
                DB::table('calendar_event_types')->insert([
                    'id' => $id,
                    'area_id' => $area->id,
                    'name' => $type['name'],
                    'color' => $type['color'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($ids as $legacyType => $id) {
                DB::table('calendar_events')
                    ->where('area_id', $area->id)
                    ->where('type', $legacyType)
                    ->update(['calendar_event_type_id' => $id]);
            }
        }

        DB::statement('ALTER TABLE calendar_events ALTER COLUMN calendar_event_type_id SET NOT NULL');
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->foreign('calendar_event_type_id')->references('id')->on('calendar_event_types')->restrictOnDelete();
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->string('type', 40)->default('other');
            $table->dropForeign(['calendar_event_type_id']);
            $table->dropColumn('calendar_event_type_id');
        });
        DB::statement('DROP INDEX IF EXISTS calendar_event_types_area_name_unique');
        Schema::dropIfExists('calendar_event_types');
    }
};
