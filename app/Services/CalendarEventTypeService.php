<?php

namespace App\Services;

use App\Models\Area;
use App\Models\CalendarEventType;
use App\Models\User;
use App\PermissionLevel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalendarEventTypeService
{
    /** @var array<string, string> */
    public const DEFAULT_TYPES = [
        'Culto especial' => '#0051F5',
        'Cruzada' => '#16A34A',
        'Festa' => '#2BD9FB',
        'Congresso' => '#7C3AED',
        'Reunião' => '#475569',
        'Ação social' => '#0D9488',
        'Vigília' => '#4338CA',
        'Retiro' => '#F59E0B',
        'Outro' => '#64748B',
    ];

    /** @var list<string> */
    private const CUSTOM_COLORS = ['#0051F5', '#16A34A', '#7C3AED', '#0D9488', '#F59E0B', '#EF4444', '#2BD9FB'];

    public function __construct(
        private AuditService $audit,
        private PermissionService $permissions,
    ) {}

    public function ensureForArea(Area $area): void
    {
        foreach (self::DEFAULT_TYPES as $name => $color) {
            CalendarEventType::query()->firstOrCreate(
                ['area_id' => $area->id, 'name' => $name],
                ['color' => $color],
            );
        }
    }

    /** @param array{name: string} $data */
    public function createForCurrentScope(array $data, User $actor): CalendarEventType
    {
        try {
            return DB::transaction(function () use ($data, $actor): CalendarEventType {
                $area = $this->currentArea($actor);
                $existing = CalendarEventType::query()
                    ->where('area_id', $area->id)
                    ->whereRaw('LOWER(name) = LOWER(?)', [$data['name']])
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                $type = CalendarEventType::query()->create([
                    'area_id' => $area->id,
                    'name' => $data['name'],
                    'color' => self::CUSTOM_COLORS[CalendarEventType::query()->where('area_id', $area->id)->count() % count(self::CUSTOM_COLORS)],
                ]);
                $this->audit->record('calendar_event_type.created', 'calendar_event_types', $type, 'area', $area->id, ['name' => $type->name]);

                return $type;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['name' => 'Já existe um tipo de evento com este nome.']);
            }

            throw $exception;
        }
    }

    private function currentArea(User $actor): Area
    {
        if ($actor->isGlobalAdministrator()) {
            return Area::query()->firstOrFail();
        }

        $church = $this->permissions->currentChurch($actor);
        abort_unless($church !== null && $this->permissions->can($actor, 'calendar', PermissionLevel::Write, $church), 403);

        return Area::query()->findOrFail($church->area_id);
    }
}
