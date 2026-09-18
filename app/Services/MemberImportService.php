<?php

namespace App\Services;

use App\Enums\MemberImportStatus;
use App\Jobs\ProcessMemberImportJob;
use App\Jobs\ValidateMemberImportJob;
use App\Models\Church;
use App\Models\MemberImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MemberImportService
{
    public function __construct(private readonly AuditService $audit) {}

    public function upload(UploadedFile $file, Church $church, User $user): MemberImport
    {
        $importId = (string) Str::uuid();
        $storedFilename = (string) Str::uuid().'.xlsx';
        $path = $file->storeAs("member-imports/{$church->id}/{$importId}", $storedFilename, 'local');

        if (! is_string($path)) {
            throw ValidationException::withMessages(['file' => 'Não foi possível armazenar o arquivo com segurança.']);
        }

        try {
            $import = DB::transaction(function () use ($importId, $church, $user, $file, $storedFilename, $path): MemberImport {
                $import = MemberImport::query()->create([
                    'id' => $importId,
                    'church_id' => $church->id,
                    'uploaded_by_user_id' => $user->id,
                    'original_filename' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'stored_filename' => $storedFilename,
                    'file_path' => $path,
                    'file_hash' => hash_file('sha256', Storage::disk('local')->path($path)),
                    'status' => MemberImportStatus::Uploaded,
                ]);

                $this->audit->record('member_import.uploaded', 'member_imports', $import, 'church', $church->id, [
                    'church_id' => $church->id,
                    'import_id' => $import->id,
                    'file_hash' => $import->file_hash,
                    'original_filename' => $import->original_filename,
                ], $user);

                ValidateMemberImportJob::dispatch($import->id)->afterCommit();

                return $import;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return $import;
    }

    public function confirm(MemberImport $import, User $user): MemberImport
    {
        return DB::transaction(function () use ($import, $user): MemberImport {
            $locked = MemberImport::query()->lockForUpdate()->findOrFail($import->id);

            if ($locked->status !== MemberImportStatus::AwaitingConfirmation || $locked->errors_count !== 0) {
                $this->audit->record('member_import.confirmation_blocked', 'member_imports', $locked, 'church', $locked->church_id, [
                    'import_id' => $locked->id,
                    'status' => $locked->status->value,
                    'errors_count' => $locked->errors_count,
                ], $user);
                throw ValidationException::withMessages(['import' => 'Esta importação não está disponível para confirmação.']);
            }

            $locked->update([
                'confirmed_by_user_id' => $user->id,
                'confirmed_at' => now(),
                'status' => MemberImportStatus::Processing,
            ]);

            $this->audit->record('member_import.confirmed', 'member_imports', $locked, 'church', $locked->church_id, [
                'import_id' => $locked->id,
                'total_rows' => $locked->total_rows,
                'new_members_count' => $locked->new_members_count,
                'updates_count' => $locked->updates_count,
            ], $user);

            ProcessMemberImportJob::dispatch($locked->id)->afterCommit();

            return $locked;
        });
    }

    public function markValidationJobFailed(string $importId, Throwable $exception): void
    {
        $import = MemberImport::query()->with('uploadedBy')->find($importId);
        if ($import === null || $import->status === MemberImportStatus::Completed) {
            return;
        }

        $import->update([
            'status' => MemberImportStatus::ValidationFailed,
            'failed_at' => now(),
            'failure_reason' => 'A validação falhou após as tentativas automáticas. Envie o arquivo novamente.',
        ]);
        $this->audit->record('member_import.technical_failure', 'member_imports', $import, 'church', $import->church_id, [
            'import_id' => $import->id,
            'failure_type' => class_basename($exception),
        ], $import->uploadedBy);
    }

    public function markProcessingJobFailed(string $importId, Throwable $exception): void
    {
        $import = MemberImport::query()->with(['uploadedBy', 'confirmedBy'])->find($importId);
        if ($import === null || $import->status === MemberImportStatus::Completed) {
            return;
        }

        $import->update([
            'status' => MemberImportStatus::ProcessingFailed,
            'failed_at' => now(),
            'failure_reason' => 'O processamento falhou sem aplicar alterações parciais. Envie uma nova planilha após revisar os dados.',
        ]);
        $this->audit->record('member_import.processing_failed', 'member_imports', $import, 'church', $import->church_id, [
            'import_id' => $import->id,
            'failure_type' => class_basename($exception),
        ], $import->confirmedBy ?? $import->uploadedBy);
    }
}
