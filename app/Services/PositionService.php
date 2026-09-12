<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Department;
use App\Models\PermissionModule;
use App\Models\Position;
use App\Models\PositionPermission;
use App\PermissionLevel;
use App\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PositionService
{
    public function __construct(
        private AuditService $audit,
        private PermissionService $permissions,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): Position
    {
        return $this->withUniqueNameHandling(fn (): Position => DB::transaction(function () use ($data): Position {
            $area = Area::query()->where('status', Status::Active->value)->lockForUpdate()->first();
            if ($area === null) {
                throw ValidationException::withMessages(['name' => 'Cadastre e ative a área antes de criar cargos.']);
            }

            $department = $this->validatedDepartment($data['department_id'] ?? null, $area->id);
            $position = Position::query()->create([
                'area_id' => $area->id,
                'department_id' => $department?->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'grants_system_access' => (bool) ($data['grants_system_access'] ?? false),
                'fixed' => false,
                'status' => Status::Active,
            ]);
            $this->audit->record('position.created', 'positions', $position, 'area', $area->id, $position->only(['name', 'department_id', 'grants_system_access', 'status']));

            return $position;
        }));
    }

    /** @param array<string, mixed> $data */
    public function update(Position $position, array $data): Position
    {
        return $this->withUniqueNameHandling(fn (): Position => DB::transaction(function () use ($position, $data): Position {
            $locked = Position::query()->with('permissions.permissionModule')->lockForUpdate()->findOrFail($position->id);
            $this->assertMutable($locked);
            $department = $this->validatedDepartment(
                $data['department_id'] ?? null,
                $locked->area_id,
                $locked->department_id,
            );
            $grantsAccess = (bool) ($data['grants_system_access'] ?? false);
            $impact = $this->impact($locked);

            if ($locked->grants_system_access && ! $grantsAccess) {
                $this->assertRevocationConfirmed($impact, (bool) ($data['confirm_access_revocation'] ?? false));
                $this->assertAdministratorsRemain($locked);
                $locked->permissions()->delete();
            }

            $before = $locked->only(['name', 'description', 'department_id', 'grants_system_access']);
            $locked->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'department_id' => $department?->id,
                'grants_system_access' => $grantsAccess,
            ]);
            $this->audit->record('position.updated', 'positions', $locked, 'area', $locked->area_id, [
                'before' => $before,
                'after' => $locked->only(['name', 'description', 'department_id', 'grants_system_access']),
                'affected' => $impact,
            ]);

            return $locked->refresh();
        }));
    }

    public function changeStatus(Position $position, Status $status, bool $confirmed = false): Position
    {
        return DB::transaction(function () use ($position, $status, $confirmed): Position {
            $locked = Position::query()->with('permissions.permissionModule')->lockForUpdate()->findOrFail($position->id);
            $this->assertMutable($locked);
            $impact = $this->impact($locked);

            if ($locked->status === Status::Active && $status === Status::Inactive && $locked->grants_system_access) {
                $this->assertRevocationConfirmed($impact, $confirmed);
                $this->assertAdministratorsRemain($locked);
            }

            $locked->update(['status' => $status]);
            $this->audit->record('position.status_changed', 'positions', $locked, 'area', $locked->area_id, ['status' => $status->value, 'affected' => $impact]);

            return $locked->refresh();
        });
    }

    /** @param array<string, string> $levels */
    public function syncPermissions(Position $position, array $levels, bool $confirmed = false): void
    {
        DB::transaction(function () use ($position, $levels, $confirmed): void {
            $locked = Position::query()->with('permissions.permissionModule')->lockForUpdate()->findOrFail($position->id);
            $this->assertMutable($locked);
            if (! $locked->grants_system_access) {
                throw ValidationException::withMessages(['permissions' => 'Habilite o acesso ao sistema antes de configurar permissões.']);
            }

            $modules = PermissionModule::query()->where('status', Status::Active->value)->orderBy('key')->get();
            if (array_diff(array_keys($levels), $modules->pluck('id')->all()) !== []) {
                throw ValidationException::withMessages(['permissions' => 'A matriz contém um módulo inválido.']);
            }

            $oldLevels = $locked->permissions->mapWithKeys(fn (PositionPermission $permission): array => [$permission->permissionModule->key => $permission->level->value])->all();
            $usersModule = $modules->firstWhere('key', 'users');
            $newUsersLevel = $usersModule ? ($levels[$usersModule->id] ?? 'none') : 'none';
            if (($oldLevels['users'] ?? 'none') === PermissionLevel::Write->value && $newUsersLevel !== PermissionLevel::Write->value) {
                $impact = $this->impact($locked);
                $this->assertRevocationConfirmed($impact, $confirmed);
                $this->assertAdministratorsRemain($locked);
            }

            $locked->permissions()->delete();
            $now = now();
            $records = [];
            foreach ($modules as $module) {
                $level = $levels[$module->id] ?? 'none';
                if ($level !== 'none') {
                    $records[] = [
                        'id' => (string) Str::uuid(),
                        'position_id' => $locked->id,
                        'permission_module_id' => $module->id,
                        'level' => $level,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            if ($records !== []) {
                PositionPermission::query()->insert($records);
            }

            $newLevels = $modules->mapWithKeys(fn (PermissionModule $module): array => [$module->key => $levels[$module->id] ?? 'none'])->all();
            $this->audit->record('position.permissions_updated', 'positions', $locked, 'area', $locked->area_id, ['before' => $oldLevels, 'after' => $newLevels]);
        });
    }

    /** @return array{members: int, users: int} */
    public function impact(Position $position): array
    {
        $query = $position->assignments()->effectiveOn(today()->toDateString())
            ->whereHas('membership', fn (Builder $query): Builder => $query->effectiveOn(today()->toDateString()));

        return [
            'members' => (clone $query)->join('member_church_memberships', 'member_church_memberships.id', '=', 'member_position_assignments.member_church_membership_id')->distinct('member_church_memberships.member_id')->count('member_church_memberships.member_id'),
            'users' => (clone $query)->whereHas('membership.member.user')->join('member_church_memberships', 'member_church_memberships.id', '=', 'member_position_assignments.member_church_membership_id')->distinct('member_church_memberships.member_id')->count('member_church_memberships.member_id'),
        ];
    }

    private function assertMutable(Position $position): void
    {
        if ($position->fixed) {
            throw ValidationException::withMessages(['position' => 'A estrutura de um cargo fixo é protegida.']);
        }
    }

    private function validatedDepartment(?string $departmentId, string $areaId, ?string $currentDepartmentId = null): ?Department
    {
        if ($departmentId === null || $departmentId === '') {
            return null;
        }

        $department = Department::query()
            ->where('area_id', $areaId)
            ->where(fn (Builder $query): Builder => $query
                ->active()
                ->when($currentDepartmentId !== null, fn (Builder $query): Builder => $query->orWhereKey($currentDepartmentId)))
            ->find($departmentId);
        if ($department === null) {
            throw ValidationException::withMessages(['department_id' => 'Selecione um departamento ativo da área.']);
        }

        return $department;
    }

    /** @param array{members: int, users: int} $impact */
    private function assertRevocationConfirmed(array $impact, bool $confirmed): void
    {
        if (($impact['members'] > 0 || $impact['users'] > 0) && ! $confirmed) {
            throw ValidationException::withMessages([
                'confirm_access_revocation' => "Confirme a revogação: {$impact['members']} membro(s) e {$impact['users']} usuário(s) serão afetados.",
            ]);
        }
    }

    private function assertAdministratorsRemain(Position $position): void
    {
        if (! $position->permissions->contains(fn (PositionPermission $permission): bool => $permission->permissionModule->key === 'users' && $permission->level === PermissionLevel::Write)) {
            return;
        }

        $churches = $position->assignments()->effectiveOn(today()->toDateString())->with('membership.church')->get()->pluck('membership.church')->filter()->unique('id');
        foreach ($churches as $church) {
            if (! $this->permissions->hasOtherChurchAdministrator($church, excludedPositionId: $position->id)) {
                throw ValidationException::withMessages(['position' => "A alteração removeria o último administrador válido de {$church->name}."]);
            }
        }
    }

    private function withUniqueNameHandling(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['name' => 'Já existe um cargo com este nome neste departamento da área.']);
            }
            throw $exception;
        }
    }
}
