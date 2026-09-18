<?php

namespace App\Jobs;

use App\Models\MemberImport;
use App\Services\MemberImportService;
use App\Services\MemberImportValidationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ValidateMemberImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 3600;

    public function __construct(public readonly string $memberImportId)
    {
        $this->onConnection('redis')->onQueue('imports');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function uniqueId(): string
    {
        return $this->memberImportId;
    }

    public function handle(MemberImportValidationService $service): void
    {
        $import = MemberImport::query()->find($this->memberImportId);
        if ($import !== null) {
            $service->validate($import);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(MemberImportService::class)->markValidationJobFailed(
            $this->memberImportId,
            $exception ?? new \RuntimeException('A validação excedeu o limite de execução.'),
        );
    }
}
