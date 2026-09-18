<?php

namespace App\Services;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\CalendarEvent;
use App\Models\Church;
use App\Models\Department;
use App\Models\Member;
use App\Models\Position;
use App\Models\User;
use App\PermissionLevel;
use App\Status;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardOverviewService
{
    public function __construct(
        private CalendarEventQueryService $calendarEvents,
        private PermissionService $permissions,
    ) {}

    /** @return array<string, mixed> */
    public function forScope(?Church $church, User $actor): array
    {
        $scope = $church?->id ?? 'overview';
        $version = (int) Cache::get('dashboard-overview:version', 1);

        return Cache::remember(
            "dashboard-overview:v{$version}:{$scope}:{$actor->id}",
            now()->addSeconds((int) config('genesis.dashboard.overview_cache_seconds', 300)),
            fn (): array => $this->build($church, $actor),
        );
    }

    /** @return array<string, mixed> */
    private function build(?Church $church, User $actor): array
    {
        $members = $this->members($church);
        $churches = Church::query()->active()->when($church, fn ($query) => $query->whereKey($church->id));
        $positions = Position::query()->active()->when($church, fn ($query) => $query->where('area_id', $church->area_id));
        $departments = Department::query()->active()->when($church, fn ($query) => $query->where('area_id', $church->area_id));

        $positionCount = (clone $positions)->count();
        $departmentCount = (clone $departments)->count();
        $churchCount = (clone $churches)->count();

        return [
            'scope' => $church?->name ?? 'Área',
            'is_consolidated' => $church === null,
            'indicators' => [
                'churches' => [
                    'value' => $churchCount,
                    'new_this_month' => (clone $churches)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                ],
                'members' => [
                    'value' => (clone $members)->count(),
                    'new_this_month' => (clone $members)->whereBetween('m.created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                ],
                'users' => ['value' => $this->usersWithAccess($church)],
                'structure' => [
                    'value' => $positionCount + $departmentCount,
                    'positions' => $positionCount,
                    'departments' => $departmentCount,
                ],
            ],
            'members_by_church' => $this->membersByChurch($church),
            'activities' => $this->activities($church),
            'birthdays' => $this->birthdaysToday($church),
            'can_view_today_events' => $this->canViewTodayEvents($actor, $church),
            'today_events' => $this->todayEvents($actor, $church),
            'structure' => [
                'area_configured' => Area::query()->where('status', Status::Active->value)
                    ->when($church, fn ($query) => $query->whereKey($church->area_id))->exists(),
                'churches' => $churchCount,
                'permissions' => DB::table('position_permissions as pp')
                    ->join('positions as p', 'p.id', '=', 'pp.position_id')
                    ->where('p.status', Status::Active->value)
                    ->when($church, fn (Builder $query): Builder => $query->where('p.area_id', $church->area_id))
                    ->distinct('pp.position_id')->count('pp.position_id'),
            ],
        ];
    }

    private function members(?Church $church): Builder
    {
        return DB::table('members as m')
            ->where('m.status', Status::Active->value)
            ->when($church, fn (Builder $query): Builder => $query->whereExists(fn (Builder $membership): Builder => $this->effectiveMembership($membership, $church)
                ->whereColumn('mcm.member_id', 'm.id')));
    }

    private function usersWithAccess(?Church $church): int
    {
        $query = DB::table('users as u')->where('u.status', Status::Active->value);

        if ($church !== null) {
            $query->whereExists(fn (Builder $access): Builder => $this->accessMembership($access, $church)
                ->whereColumn('mcm.member_id', 'u.member_id'));
        } else {
            $query->where(fn (Builder $query): Builder => $query
                ->where('u.is_global_administrator', true)
                ->orWhereExists(fn (Builder $access): Builder => $this->accessMembership($access, null)
                    ->whereColumn('mcm.member_id', 'u.member_id')));
        }

        return $query->count();
    }

    /** @return list<array{name: string, age: int}> */
    private function birthdaysToday(?Church $church): array
    {
        $today = CarbonImmutable::today(config('genesis.calendar.timezone'));
        $areaId = $church?->area_id ?? Area::query()->value('id');

        return DB::table('members as m')
            ->where('m.status', Status::Active->value)
            ->whereNotNull('m.birth_date')
            ->whereRaw('EXTRACT(MONTH FROM m.birth_date) = ?', [$today->month])
            ->whereRaw('EXTRACT(DAY FROM m.birth_date) = ?', [$today->day])
            ->whereExists(function (Builder $membership) use ($church, $areaId, $today): Builder {
                return $membership->selectRaw('1')->from('member_church_memberships as mcm')
                    ->join('churches as ch', 'ch.id', '=', 'mcm.church_id')
                    ->whereColumn('mcm.member_id', 'm.id')
                    ->where('mcm.status', Status::Active->value)
                    ->where('ch.status', Status::Active->value)
                    ->when($areaId, fn (Builder $query): Builder => $query->where('ch.area_id', $areaId))
                    ->when($church, fn (Builder $query): Builder => $query->where('mcm.church_id', $church->id))
                    ->whereDate('mcm.joined_at', '<=', $today)
                    ->where(fn (Builder $query): Builder => $query->whereNull('mcm.ended_at')->orWhereDate('mcm.ended_at', '>=', $today));
            })
            ->orderBy('m.name')
            ->limit(8)
            ->get(['m.name', 'm.birth_date'])
            ->map(fn (object $member): array => [
                'name' => $member->name,
                'age' => $today->year - CarbonImmutable::parse($member->birth_date)->year,
            ])->all();
    }

    private function canViewTodayEvents(User $actor, ?Church $church): bool
    {
        return $actor->isGlobalAdministrator()
            || ($church !== null && $this->permissions->can($actor, 'calendar', PermissionLevel::Read, $church));
    }

    /** @return list<array{title: string, type: string, time: string, scope: string, status: string, url: string}> */
    private function todayEvents(User $actor, ?Church $church): array
    {
        if (! $this->canViewTodayEvents($actor, $church)) {
            return [];
        }

        $timezone = config('genesis.calendar.timezone');
        $start = CarbonImmutable::today($timezone);
        $end = $start->addDay();

        return $this->calendarEvents->visibleBetween($actor, $church, $start, $end)
            ->orderBy('starts_at')
            ->get()
            ->map(function (CalendarEvent $event) use ($timezone): array {
                $startsAt = $event->starts_at->setTimezone($timezone);
                $endsAt = $event->ends_at->setTimezone($timezone);

                return [
                    'title' => $event->title,
                    'type' => $event->eventType->name,
                    'time' => $event->all_day ? 'Dia inteiro' : $startsAt->format('H:i').' – '.$endsAt->format('H:i'),
                    'scope' => $event->church?->name ?? $event->area->name,
                    'status' => $event->status->label(),
                    'url' => route('calendar.events.show', $event),
                ];
            })->all();
    }

    private function effectiveMembership(Builder $query, Church $church): Builder
    {
        return $query->selectRaw('1')->from('member_church_memberships as mcm')
            ->where('mcm.church_id', $church->id)
            ->where('mcm.status', Status::Active->value)
            ->whereDate('mcm.joined_at', '<=', today())
            ->where(fn (Builder $query): Builder => $query->whereNull('mcm.ended_at')->orWhereDate('mcm.ended_at', '>=', today()));
    }

    private function accessMembership(Builder $query, ?Church $church): Builder
    {
        return $query->selectRaw('1')->from('member_church_memberships as mcm')
            ->join('members as m', 'm.id', '=', 'mcm.member_id')
            ->join('member_position_assignments as mpa', 'mpa.member_church_membership_id', '=', 'mcm.id')
            ->join('positions as p', 'p.id', '=', 'mpa.position_id')
            ->where('m.status', Status::Active->value)
            ->where('mcm.status', Status::Active->value)
            ->whereDate('mcm.joined_at', '<=', today())
            ->where(fn (Builder $query): Builder => $query->whereNull('mcm.ended_at')->orWhereDate('mcm.ended_at', '>=', today()))
            ->where('mpa.status', Status::Active->value)
            ->whereDate('mpa.started_at', '<=', today())
            ->where(fn (Builder $query): Builder => $query->whereNull('mpa.ended_at')->orWhereDate('mpa.ended_at', '>=', today()))
            ->where('p.status', Status::Active->value)
            ->where('p.grants_system_access', true)
            ->when($church, fn (Builder $query): Builder => $query->where('mcm.church_id', $church->id));
    }

    /** @return list<array{name: string, value: int}> */
    private function membersByChurch(?Church $church): array
    {
        return DB::table('churches as ch')
            ->leftJoin('member_church_memberships as mcm', function ($join): void {
                $join->on('mcm.church_id', '=', 'ch.id')
                    ->where('mcm.status', Status::Active->value)
                    ->whereDate('mcm.joined_at', '<=', today())
                    ->where(fn ($query) => $query->whereNull('mcm.ended_at')->orWhereDate('mcm.ended_at', '>=', today()));
            })
            ->leftJoin('members as m', function ($join): void {
                $join->on('m.id', '=', 'mcm.member_id')->where('m.status', Status::Active->value);
            })
            ->where('ch.status', Status::Active->value)
            ->when($church, fn (Builder $query): Builder => $query->where('ch.id', $church->id))
            ->selectRaw('ch.name, COUNT(DISTINCT m.id) AS value')
            ->groupBy('ch.id', 'ch.name')->orderByDesc('value')->orderBy('ch.name')->get()
            ->map(fn (object $row): array => ['name' => $row->name, 'value' => (int) $row->value])->all();
    }

    /** @return list<array{label: string, subject: ?string, actor: string, occurred_at: string}> */
    private function activities(?Church $church): array
    {
        $labels = [
            'member.created' => 'Membro cadastrado',
            'member.updated' => 'Membro atualizado',
            'church.created' => 'Igreja cadastrada',
            'church.updated' => 'Igreja atualizada',
            'user.created' => 'Usuário criado',
            'user.status_changed' => 'Acesso de usuário atualizado',
            'position.created' => 'Cargo criado',
            'position.updated' => 'Cargo atualizado',
            'position.permissions_updated' => 'Permissões atualizadas',
            'department.created' => 'Departamento criado',
            'department.updated' => 'Departamento atualizado',
        ];

        $logs = AuditLog::query()->whereIn('action', array_keys($labels))
            ->when($church, fn ($query) => $query->where(function ($query) use ($church): void {
                $query->where(fn ($query) => $query->where('scope_type', 'church')->where('scope_id', $church->id))
                    ->orWhere(fn ($query) => $query->where('scope_type', 'area')->where('scope_id', $church->area_id)->whereIn('resource', ['positions', 'departments']))
                    ->orWhere(fn ($query) => $query->where('resource', 'members')->whereIn('record_id', DB::table('member_church_memberships')->where('church_id', $church->id)->selectRaw('member_id::text')))
                    ->orWhere(fn ($query) => $query->where('resource', 'users')->whereIn('record_id', DB::table('users')->whereIn('member_id', DB::table('member_church_memberships')->where('church_id', $church->id)->select('member_id'))->selectRaw('id::text')));
            }))
            ->latest()->limit(7)->get();

        return $logs->map(fn (AuditLog $log): array => [
            'label' => $this->activityLabel($log, $labels),
            'subject' => $this->activitySubject($log),
            'actor' => $log->actor_name ?: 'Sistema',
            'occurred_at' => $log->created_at->toIso8601String(),
        ])->all();
    }

    /** @param array<string, string> $labels */
    private function activityLabel(AuditLog $log, array $labels): string
    {
        if ($log->action !== 'user.status_changed') {
            return $labels[$log->action];
        }

        return match ($log->details['status'] ?? null) {
            Status::Active->value => 'Usuário ativado',
            Status::Inactive->value => 'Usuário inativado',
            default => $labels[$log->action],
        };
    }

    private function activitySubject(AuditLog $log): ?string
    {
        if (filled($log->details['name'] ?? null)) {
            return (string) $log->details['name'];
        }

        if (! $log->record_id) {
            return null;
        }

        return match ($log->resource) {
            'members' => Member::query()->whereKey($log->record_id)->value('name'),
            'churches' => Church::query()->whereKey($log->record_id)->value('name'),
            'users' => User::query()->whereKey($log->record_id)->value('display_name'),
            'positions' => Position::query()->whereKey($log->record_id)->value('name'),
            'departments' => Department::query()->whereKey($log->record_id)->value('name'),
            default => null,
        };
    }
}
