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
            'dashboard' => ['name' => 'Painel', 'description' => 'Acesso ao painel inicial.', 'category' => 'Geral'],
            'members' => ['name' => 'Membros', 'description' => 'Gestão de membros.', 'category' => 'Pessoas'],
            'users' => ['name' => 'Usuários', 'description' => 'Gestão de contas de acesso.', 'category' => 'Acesso'],
            'access_roles' => ['name' => 'Grupos de acesso', 'description' => 'Gestão de grupos e permissões.', 'category' => 'Acesso'],
            'positions' => ['name' => 'Cargos', 'description' => 'Gestão de cargos ministeriais.', 'category' => 'Organização'],
            'areas' => ['name' => 'Áreas', 'description' => 'Gestão de áreas administrativas.', 'category' => 'Organização'],
            'churches' => ['name' => 'Igrejas', 'description' => 'Gestão de igrejas.', 'category' => 'Organização'],
            'departments' => ['name' => 'Departamentos', 'description' => 'Gestão de departamentos.', 'category' => 'Organização'],
            'audit' => ['name' => 'Auditoria', 'description' => 'Consulta de registros de auditoria.', 'category' => 'Segurança'],
        ];
    }
}
