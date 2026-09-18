<?php

namespace Database\Seeders;

use App\Models\PermissionModule;
use App\Status;
use Illuminate\Database\Seeder;

class PermissionModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PermissionModule::query()->whereIn('key', ['finance.accounts', 'finance.categories'])->delete();

        foreach (self::definitions() as $key => $attributes) {
            PermissionModule::query()->updateOrCreate(
                ['key' => $key],
                [...$attributes, 'status' => Status::Active],
            );
        }
    }

    /**
     * @return array<string, array{name: string, description: string, category: string}>
     */
    public static function definitions(): array
    {
        return [
            'dashboard' => ['name' => 'Painel', 'description' => 'Acesso ao painel inicial.', 'category' => 'Principal'],
            'calendar' => ['name' => 'Agenda', 'description' => 'Consulta e gestão de eventos da agenda.', 'category' => 'Principal'],
            'members' => ['name' => 'Membros', 'description' => 'Gestão de membros.', 'category' => 'Cadastros'],
            'users' => ['name' => 'Usuários', 'description' => 'Gestão de contas de acesso.', 'category' => 'Administração'],
            'positions' => ['name' => 'Cargos e permissões', 'description' => 'Gestão de cargos e das permissões que eles concedem.', 'category' => 'Administração'],
            'areas' => ['name' => 'Áreas', 'description' => 'Gestão de áreas administrativas.', 'category' => 'Cadastros'],
            'churches' => ['name' => 'Igrejas', 'description' => 'Gestão de igrejas.', 'category' => 'Cadastros'],
            'departments' => ['name' => 'Departamentos', 'description' => 'Gestão de departamentos da área.', 'category' => 'Administração'],
            'audit' => ['name' => 'Auditoria', 'description' => 'Consulta de registros de auditoria.', 'category' => 'Administração'],
            'finance.overview' => ['name' => 'Visão financeira', 'description' => 'Consulta consolidada de saldos, entradas e saídas.', 'category' => 'Financeiro'],
            'finance.transactions' => ['name' => 'Movimentações financeiras', 'description' => 'Gestão de entradas, despesas, ajustes e transferências.', 'category' => 'Financeiro'],
            'finance.tithes' => ['name' => 'Dízimos', 'description' => 'Gestão do recebimento de dízimos.', 'category' => 'Financeiro'],
            'finance.reports' => ['name' => 'Relatórios financeiros', 'description' => 'Consulta de demonstrativos e exportações financeiras.', 'category' => 'Financeiro'],
        ];
    }
}
