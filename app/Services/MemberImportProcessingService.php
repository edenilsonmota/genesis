<?php

namespace App\Services;

use App\Enums\MemberImportOperation;
use App\Enums\MemberImportRowStatus;
use App\Enums\MemberImportStatus;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\MemberImport;
use App\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class MemberImportProcessingService
{
    public function __construct(private readonly AuditService $audit) {}

    public function process(MemberImport $import): void
    {
        $import->loadMissing(['church', 'uploadedBy', 'confirmedBy']);
        if ($import->status === MemberImportStatus::Completed) {
            return;
        }

        if ($import->status !== MemberImportStatus::Processing || $import->errors_count !== 0) {
            throw new LogicException('A importação não está pronta para processamento.');
        }

        $this->audit->record(
            'member_import.processing_started',
            'member_imports',
            $import,
            'church',
            $import->church_id,
            $this->auditDetails($import),
            $import->confirmedBy ?? $import->uploadedBy,
        );

        $createdIds = [];
        $updated = [];

        try {
            DB::transaction(function () use ($import, &$createdIds, &$updated): void {
                $lockedImport = MemberImport::query()->lockForUpdate()->findOrFail($import->id);
                if ($lockedImport->status !== MemberImportStatus::Processing || $lockedImport->errors_count !== 0) {
                    throw new LogicException('A importação foi alterada e não pode ser processada.');
                }

                $rows = $lockedImport->rows()
                    ->where('status', MemberImportRowStatus::Valid->value)
                    ->orderBy('row_number')
                    ->lockForUpdate()
                    ->get();

                if ($rows->count() !== $lockedImport->total_rows) {
                    throw ValidationException::withMessages(['import' => 'A prévia validada está incompleta. Envie o arquivo novamente.']);
                }

                foreach ($rows->chunk(250) as $chunk) {
                    foreach ($chunk as $row) {
                        $payload = $row->normalized_payload ?? [];

                        if ($row->operation === MemberImportOperation::Create) {
                            $this->assertCpfAvailable((string) ($payload['cpf'] ?? ''));
                            $member = Member::query()->create([...$payload, 'status' => Status::Active]);
                            MemberChurchMembership::query()->create([
                                'member_id' => $member->id,
                                'church_id' => $lockedImport->church_id,
                                'status' => Status::Active,
                                'is_primary' => true,
                                'joined_at' => today(),
                            ]);
                            $row->update(['member_id' => $member->id, 'status' => MemberImportRowStatus::Processed]);
                            $createdIds[] = $member->id;

                            continue;
                        }

                        $member = Member::query()->lockForUpdate()->find($row->member_id);
                        if ($member === null || $member->status !== Status::Active || ! $member->memberships()->effectiveOn(today()->toDateString())->where('church_id', $lockedImport->church_id)->exists()) {
                            throw ValidationException::withMessages(['import' => "O membro da linha {$row->row_number} não pertence mais à igreja selecionada."]);
                        }

                        if (isset($payload['cpf']) && $payload['cpf'] !== $member->cpf) {
                            $this->assertCpfAvailable((string) $payload['cpf'], $member->id);
                        }

                        $member->update($payload);
                        $changedFields = array_keys(array_intersect_key($member->getChanges(), array_flip(array_keys($payload))));
                        $row->update(['status' => MemberImportRowStatus::Processed]);
                        $updated[] = ['member_id' => $member->id, 'fields' => $changedFields];
                    }
                }

                $lockedImport->update([
                    'status' => MemberImportStatus::Completed,
                    'processed_at' => now(),
                    'completed_at' => now(),
                    'failed_at' => null,
                    'failure_reason' => null,
                ]);
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['import' => 'Os dados mudaram após a validação. Envie uma nova planilha para evitar duplicidade.']);
            }

            throw $exception;
        }

        $import->refresh();
        $this->audit->record(
            'member_import.processing_completed',
            'member_imports',
            $import,
            'church',
            $import->church_id,
            $this->auditDetails($import) + [
                'created_member_ids' => $createdIds,
                'updated_members' => $updated,
            ],
            $import->confirmedBy ?? $import->uploadedBy,
        );
    }

    private function assertCpfAvailable(string $cpf, ?string $ignoredMemberId = null): void
    {
        $exists = Member::query()
            ->where('cpf', $cpf)
            ->when($ignoredMemberId, fn ($query, string $id) => $query->whereKeyNot($id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['import' => 'Um CPF da importação passou a pertencer a outro membro. Envie uma nova planilha.']);
        }
    }

    /** @return array<string, mixed> */
    private function auditDetails(MemberImport $import): array
    {
        return [
            'church_id' => $import->church_id,
            'import_id' => $import->id,
            'file_hash' => $import->file_hash,
            'original_filename' => $import->original_filename,
            'total_rows' => $import->total_rows,
            'new_members_count' => $import->new_members_count,
            'updates_count' => $import->updates_count,
            'errors_count' => $import->errors_count,
        ];
    }
}
