<?php

namespace App\Services\Finance;

use App\Enums\FinancialCategoryType;
use App\Models\Area;
use App\Models\FinancialCategory;
use App\Models\User;
use App\PermissionLevel;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialCategoryProvisioningService
{
    /** @var array<string, list<string>> */
    public const DEFAULT_NAMES = [
        'income' => [
            'Dízimos', 'Ofertas', 'Ofertas missionárias', 'Doações', 'Campanhas',
            'Contribuições para eventos', 'Inscrições de eventos', 'Vendas e cantina',
            'Aluguéis/cessão de espaço', 'Transferências recebidas', 'Outras receitas',
        ],
        'expense' => [
            'Energia elétrica', 'Água e esgoto', 'Internet e telefone', 'Aluguel',
            'Manutenção e reparos', 'Material de limpeza', 'Material de escritório',
            'Material ministerial', 'Som, mídia e tecnologia', 'Obras e reformas',
            'Eventos e congressos', 'Missões', 'Ação social', 'Transporte e combustível',
            'Alimentação', 'Ajuda de custo', 'Taxas bancárias', 'Impostos e obrigações',
            'Serviços profissionais', 'Outras despesas',
        ],
    ];

    public function __construct(
        private AuditService $audit,
        private PermissionService $permissions,
    ) {}

    public function ensureForArea(Area $area): void
    {
        foreach (self::DEFAULT_NAMES as $type => $names) {
            foreach ($names as $name) {
                FinancialCategory::query()->firstOrCreate(
                    ['area_id' => $area->id, 'type' => $type, 'name' => $name],
                    ['fixed' => true, 'status' => Status::Active],
                );
            }
        }
    }

    /** @param array{name: string, type: string, area_id: string} $data */
    public function createFromTransaction(array $data, User $actor): FinancialCategory
    {
        try {
            return DB::transaction(function () use ($data, $actor): FinancialCategory {
                $area = Area::query()->whereKey($data['area_id'])->where('status', Status::Active->value)->lockForUpdate()->first();
                if ($area === null) {
                    throw ValidationException::withMessages(['area_id' => 'A área selecionada não está ativa.']);
                }

                if (! $actor->isGlobalAdministrator()
                    && ($this->permissions->currentChurch($actor)?->area_id !== $area->id
                        || ! $this->permissions->can($actor, 'finance.transactions', PermissionLevel::Write))) {
                    throw ValidationException::withMessages(['area_id' => 'Você não pode criar categorias para esta área.']);
                }

                $existing = FinancialCategory::query()
                    ->where('area_id', $area->id)
                    ->where('type', $data['type'])
                    ->whereRaw('LOWER(name) = LOWER(?)', [$data['name']])
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ($existing->status !== Status::Active) {
                        throw ValidationException::withMessages(['name' => 'Esta categoria existe, mas está inativa.']);
                    }

                    return $existing;
                }

                $category = FinancialCategory::query()->create([
                    'area_id' => $area->id,
                    'name' => $data['name'],
                    'type' => FinancialCategoryType::from($data['type']),
                    'fixed' => false,
                    'status' => Status::Active,
                ]);

                $this->audit->record('financial_category.created_from_transaction', 'financial_categories', $category, 'area', $area->id, [
                    'name' => $category->name,
                    'type' => $category->type->value,
                ]);

                return $category;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['name' => 'Já existe uma categoria com este nome para este tipo de movimentação.']);
            }

            throw $exception;
        }
    }
}
