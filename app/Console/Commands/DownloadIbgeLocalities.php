<?php

namespace App\Console\Commands;

use App\Services\IbgeLocalitiesService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('ibge:download-localities {--output=database/data/ibge-localities.json : Caminho de destino relativo à raiz do projeto}')]
#[Description('Baixa e normaliza estados e municípios da API de Localidades do IBGE')]
class DownloadIbgeLocalities extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(IbgeLocalitiesService $localities): int
    {
        try {
            $this->components->info('Baixando localidades da API do IBGE...');

            $snapshot = $localities->download();
            $path = $localities->save($snapshot, (string) $this->option('output'));

            $this->components->twoColumnDetail('Estados', (string) count($snapshot['states']));
            $this->components->twoColumnDetail('Municípios', (string) count($snapshot['cities']));
            $this->components->success("Snapshot salvo em {$path}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('Não foi possível atualizar o snapshot: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
