<?php

namespace App\Jobs;

use App\Models\MemberImport;
use App\Services\MemberImportProcessingService;
use App\Services\MemberImportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessMemberImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 3600;

    public function __construct(public readonly string $memberImportId)
    {
        $this->onConnection('redis')->onQueue('imports');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function uniqueId(): string
    {
        return $this->memberImportId;
    }

    public function handle(MemberImportProcessingService $service): void
    {
        $import = MemberImport::query()->find($this->memberImportId);
        if ($import !== null) {
            $service->process($import);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(MemberImportService::class)->markProcessingJobFailed(
            $this->memberImportId,
            $exception ?? new \RuntimeException('O processamento excedeu o limite de execução.'),
        );
    }
}
