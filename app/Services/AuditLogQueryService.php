<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Church;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AuditLogQueryService
{
    /** @param array<string, mixed> $filters */
    public function filtered(?Church $church, array $filters): Builder
    {
        return $this->forScope($church)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->where('actor_name', 'ILIKE', "%{$search}%")
                    ->orWhere('actor_username', 'ILIKE', "%{$search}%")
                    ->orWhere('action', 'ILIKE', "%{$search}%")
                    ->orWhere('resource', 'ILIKE', "%{$search}%")
                    ->orWhere('record_id', 'ILIKE', "%{$search}%")))
            ->when($filters['action'] ?? null, fn (Builder $query, string $action): Builder => $query->where('action', $action))
            ->when($filters['resource'] ?? null, fn (Builder $query, string $resource): Builder => $query->where('resource', $resource))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date));
    }

    public function forScope(?Church $church): Builder
    {
        $query = AuditLog::query();

        if ($church === null) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($church): void {
            $query
                ->where(fn (Builder $query): Builder => $query
                    ->where('scope_type', 'church')
                    ->where('scope_id', $church->id))
                ->orWhere(fn (Builder $query): Builder => $query
                    ->where('scope_type', 'area')
                    ->where('scope_id', $church->area_id)
                    ->whereIn('resource', ['areas', 'positions', 'departments', 'calendar_events', 'calendar_event_types', 'financial_categories']))
                ->orWhere(fn (Builder $query): Builder => $query
                    ->where('resource', 'members')
                    ->whereIn('record_id', DB::table('member_church_memberships')
                        ->where('church_id', $church->id)
                        ->selectRaw('member_id::text')))
                ->orWhere(fn (Builder $query): Builder => $query
                    ->where('resource', 'users')
                    ->whereIn('record_id', DB::table('users')
                        ->whereIn('member_id', DB::table('member_church_memberships')
                            ->where('church_id', $church->id)
                            ->select('member_id'))
                        ->selectRaw('id::text')))
                ->orWhere(fn (Builder $query): Builder => $query
                    ->where('resource', 'financial_transactions')
                    ->whereIn('record_id', DB::table('financial_transactions as ft')
                        ->join('financial_movements as fm', 'fm.financial_transaction_id', '=', 'ft.id')
                        ->join('financial_accounts as fa', 'fa.id', '=', 'fm.financial_account_id')
                        ->where('fa.church_id', $church->id)
                        ->selectRaw('ft.id::text')));
        });
    }

    public function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? str($action)->replace(['.', '_'], ' ')->headline()->toString();
    }

    public function resourceLabel(string $resource): string
    {
        return self::RESOURCE_LABELS[$resource] ?? str($resource)->replace('_', ' ')->headline()->toString();
    }

    /** @return array<string, string> */
    public function actionLabels(): array
    {
        return self::ACTION_LABELS;
    }

    /** @return array<string, string> */
    public function resourceLabels(): array
    {
        return self::RESOURCE_LABELS;
    }

    private const ACTION_LABELS = [
        'global_administrator.created' => 'Administrador global criado',
        'global_administrator.restored' => 'Administrador global restaurado',
        'area.created' => 'Área criada',
        'area.updated' => 'Área atualizada',
        'church.created' => 'Igreja criada',
        'church.updated' => 'Igreja atualizada',
        'church.inactivated' => 'Igreja inativada',
        'member.created' => 'Membro criado',
        'member.updated' => 'Membro atualizado',
        'member.inactivated' => 'Membro inativado',
        'membership.created' => 'Vínculo criado',
        'membership.primary_changed' => 'Igreja principal alterada',
        'membership.ended' => 'Vínculo encerrado',
        'member_position.assigned' => 'Cargo atribuído ao membro',
        'member_position.ended' => 'Atribuição de cargo encerrada',
        'department.created' => 'Departamento criado',
        'department.updated' => 'Departamento atualizado',
        'department.status_changed' => 'Status do departamento alterado',
        'position.created' => 'Cargo criado',
        'position.updated' => 'Cargo atualizado',
        'position.status_changed' => 'Status do cargo alterado',
        'position.permissions_updated' => 'Permissões do cargo atualizadas',
        'user.created' => 'Usuário criado',
        'user.status_changed' => 'Status do usuário alterado',
        'user.temporary_password_generated' => 'Senha temporária gerada',
        'user.password_change_required' => 'Troca de senha exigida',
        'user.profile_updated' => 'Perfil atualizado',
        'user.username_updated' => 'Nome de usuário atualizado',
        'user.password_changed' => 'Senha alterada',
        'calendar_event.created' => 'Evento criado',
        'calendar_event.updated' => 'Evento atualizado',
        'calendar_event.rescheduled' => 'Evento reagendado',
        'calendar_event.cancelled' => 'Evento cancelado',
        'calendar_event_type.created' => 'Tipo de evento criado',
        'financial_category.created_from_transaction' => 'Categoria financeira criada',
        'financial_transaction.created' => 'Movimentação criada',
        'financial_transaction.transfer_created' => 'Transferência criada',
        'financial_transaction.updated' => 'Movimentação atualizada',
        'financial_transaction.settled' => 'Movimentação liquidada',
        'financial_transaction.cancelled' => 'Movimentação cancelada',
        'financial_transaction.reversed' => 'Movimentação estornada',
        'financial_transaction.blocked' => 'Operação financeira bloqueada',
        'financial_tithe.created' => 'Dízimo registrado',
        'financial_tithe.details_updated' => 'Dízimo atualizado',
        'financial_tithe.reversed' => 'Dízimo estornado',
    ];

    private const RESOURCE_LABELS = [
        'system' => 'Sistema',
        'areas' => 'Área',
        'churches' => 'Igreja',
        'members' => 'Membro',
        'member_church_memberships' => 'Vínculo com igreja',
        'member_position_assignments' => 'Cargo do membro',
        'departments' => 'Departamento',
        'positions' => 'Cargo',
        'users' => 'Usuário',
        'calendar_events' => 'Evento da agenda',
        'calendar_event_types' => 'Tipo de evento',
        'financial_categories' => 'Categoria financeira',
        'financial_transactions' => 'Movimentação financeira',
    ];
}
