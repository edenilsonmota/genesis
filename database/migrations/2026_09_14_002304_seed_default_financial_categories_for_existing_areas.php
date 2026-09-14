<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'income' => ['Dízimos', 'Ofertas', 'Ofertas missionárias', 'Doações', 'Campanhas', 'Contribuições para eventos', 'Inscrições de eventos', 'Vendas e cantina', 'Aluguéis/cessão de espaço', 'Transferências recebidas', 'Outras receitas'],
            'expense' => ['Energia elétrica', 'Água e esgoto', 'Internet e telefone', 'Aluguel', 'Manutenção e reparos', 'Material de limpeza', 'Material de escritório', 'Material ministerial', 'Som, mídia e tecnologia', 'Obras e reformas', 'Eventos e congressos', 'Missões', 'Ação social', 'Transporte e combustível', 'Alimentação', 'Ajuda de custo', 'Taxas bancárias', 'Impostos e obrigações', 'Serviços profissionais', 'Outras despesas'],
        ];
        $now = now();

        DB::table('areas')->orderBy('id')->each(function (object $area) use ($defaults, $now): void {
            foreach ($defaults as $type => $names) {
                foreach ($names as $name) {
                    $exists = DB::table('financial_categories')
                        ->where('area_id', $area->id)
                        ->where('type', $type)
                        ->whereRaw('LOWER(name) = LOWER(?)', [$name])
                        ->exists();

                    if (! $exists) {
                        DB::table('financial_categories')->insert([
                            'id' => (string) Str::uuid(),
                            'area_id' => $area->id,
                            'name' => $name,
                            'type' => $type,
                            'fixed' => true,
                            'status' => 'active',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Categorias podem ter sido usadas em movimentações; a reversão preserva o histórico.
    }
};
