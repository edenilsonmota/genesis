<?php

namespace App\Services;

use App\Enums\MemberImportOperation;
use App\Enums\MemberImportRowStatus;
use App\Enums\MemberImportStatus;
use App\Enums\Sex;
use App\Exceptions\MemberImportValidationException;
use App\Models\City;
use App\Models\Member;
use App\Models\MemberImport;
use App\Rules\ValidCpf;
use App\Status;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class MemberImportValidationService
{
    public function __construct(
        private readonly MemberImportSpreadsheetService $spreadsheets,
        private readonly PostalCodeLookupService $postalCodes,
        private readonly AuditService $audit,
    ) {}

    public function validate(MemberImport $import): void
    {
        $import->loadMissing(['church', 'uploadedBy']);
        if (! in_array($import->status, [MemberImportStatus::Uploaded, MemberImportStatus::Validating], true)) {
            return;
        }

        $import->update([
            'status' => MemberImportStatus::Validating,
            'failure_reason' => null,
            'failed_at' => null,
        ]);
        $this->audit->record(
            'member_import.validation_started',
            'member_imports',
            $import,
            'church',
            $import->church_id,
            $this->auditDetails($import),
            $import->uploadedBy,
        );

        try {
            $sourceRows = $this->spreadsheets->rows($import->file_path);
            $normalizedRows = $this->normalizeRows($sourceRows);
            $postalCodeResults = $this->resolvePostalCodes($normalizedRows);
            $validatedRows = $this->validateRows($import, $normalizedRows, $postalCodeResults);
            $this->persistValidation($import, $validatedRows);
        } catch (MemberImportValidationException $exception) {
            $this->persistStructuralFailure($import, $exception);
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * @param  list<array{row_number: int, values: array<string, mixed>, formula_fields: list<string>}>  $sourceRows
     * @return list<array{row_number: int, operation: MemberImportOperation, member_id: string|null, payload: array<string, mixed>, errors: list<array{field: string, message: string, value?: string|null}>}>
     */
    private function normalizeRows(array $sourceRows): array
    {
        return array_map(function (array $row): array {
            $values = $row['values'];
            $memberId = $this->nullableString($values['id_membro']);
            $birthDate = $this->normalizeDate($values['data_nascimento']);
            $errors = array_map(
                fn (string $field): array => ['field' => $field, 'message' => 'Fórmulas não são permitidas no arquivo.', 'value' => null],
                $row['formula_fields'],
            );

            if ($birthDate === false) {
                $errors[] = ['field' => 'data_nascimento', 'message' => 'Informe uma data real no formato dd/mm/aaaa.', 'value' => $this->nullableString($values['data_nascimento'])];
                $birthDate = null;
            }

            return [
                'row_number' => $row['row_number'],
                'operation' => $memberId === null ? MemberImportOperation::Create : MemberImportOperation::Update,
                'member_id' => $memberId,
                'payload' => [
                    'name' => Str::squish((string) ($values['nome'] ?? '')),
                    'cpf' => $this->digits($values['cpf']),
                    'email' => ($email = Str::lower(trim((string) ($values['email'] ?? '')))) !== '' ? $email : null,
                    'phone' => $this->digits($values['telefone']),
                    'birth_date' => $birthDate,
                    'sex' => $this->normalizeSex($values['sexo']),
                    'postal_code' => $this->digits($values['cep']),
                    'number' => $this->nullableString($values['numero']),
                    'complement' => $this->nullableString($values['complemento']),
                ],
                'errors' => $errors,
            ];
        }, $sourceRows);
    }

    /**
     * @param  list<array{payload: array<string, mixed>}>  $rows
     * @return array<string, array{ibge_code: int, street: string, neighborhood: string, complement: string}|null>
     */
    private function resolvePostalCodes(array $rows): array
    {
        $postalCodes = collect($rows)
            ->pluck('payload.postal_code')
            ->filter(fn (mixed $postalCode): bool => is_string($postalCode) && strlen($postalCode) === 8)
            ->unique();
        $results = [];

        foreach ($postalCodes as $postalCode) {
            try {
                $results[$postalCode] = $this->postalCodes->lookup($postalCode);
            } catch (Throwable $exception) {
                throw new MemberImportValidationException(
                    'O serviço de CEP está indisponível. A validação pode ser tentada novamente com um novo envio.',
                    [['field' => 'cep', 'message' => 'Não foi possível consultar os CEPs agora.', 'value' => $this->maskPostalCode($postalCode)]],
                );
            }
        }

        return $results;
    }

    /**
     * @param  list<array{row_number: int, operation: MemberImportOperation, member_id: string|null, payload: array<string, mixed>, errors: list<array{field: string, message: string, value?: string|null}>}>  $rows
     * @param  array<string, array{ibge_code: int, street: string, neighborhood: string, complement: string}|null>  $postalCodeResults
     * @return list<array{row_number: int, operation: MemberImportOperation, member_id: string|null, normalized_payload: array<string, mixed>, errors: list<array{field: string, message: string, value?: string|null}>, status: MemberImportRowStatus}>
     */
    private function validateRows(MemberImport $import, array $rows, array $postalCodeResults): array
    {
        $seenCpfs = [];
        $seenIds = [];
        $result = [];

        foreach ($rows as $row) {
            $payload = $row['payload'];
            $errors = $row['errors'];
            $member = null;

            if ($row['operation'] === MemberImportOperation::Update) {
                if (! Str::isUuid((string) $row['member_id'])) {
                    $errors[] = ['field' => 'id_membro', 'message' => 'O identificador do membro não é um UUID válido.', 'value' => null];
                } else {
                    $member = Member::query()->find($row['member_id']);
                    if ($member === null) {
                        $errors[] = ['field' => 'id_membro', 'message' => 'O membro informado não existe.', 'value' => null];
                    } elseif ($member->status !== Status::Active || ! $member->memberships()->effectiveOn(today()->toDateString())->where('church_id', $import->church_id)->exists()) {
                        $errors[] = ['field' => 'id_membro', 'message' => 'O membro não possui vínculo ativo com a igreja desta importação.', 'value' => null];
                    }
                }

                if (isset($seenIds[$row['member_id']])) {
                    $errors[] = ['field' => 'id_membro', 'message' => 'O mesmo membro aparece mais de uma vez no arquivo.', 'value' => null];
                }
                $seenIds[$row['member_id']] = true;
            }

            $validator = Validator::make($payload, [
                'name' => ['required', 'string', 'max:255'],
                'cpf' => [$row['operation'] === MemberImportOperation::Create ? 'required' : 'nullable', 'digits:11', new ValidCpf, Rule::unique(Member::class, 'cpf')->ignore($member)],
                'email' => ['nullable', 'email:rfc', 'max:255'],
                'phone' => ['nullable', 'digits_between:10,11'],
                'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                'sex' => ['nullable', Rule::enum(Sex::class)],
                'postal_code' => [$row['operation'] === MemberImportOperation::Create ? 'required' : 'nullable', 'digits:8'],
                'number' => ['nullable', 'string', 'max:30'],
                'complement' => ['nullable', 'string', 'max:255'],
            ], [
                'name.required' => 'O nome é obrigatório.',
                'cpf.required' => 'O CPF é obrigatório para um novo membro.',
                'cpf.unique' => 'O CPF já pertence a outro membro. Trate um novo vínculo pela tela de Membros.',
                'postal_code.required' => 'O CEP é obrigatório para novos membros porque o cadastro atual exige uma cidade.',
                'postal_code.digits' => 'O CEP deve possuir oito dígitos.',
                'birth_date.before_or_equal' => 'A data de nascimento não pode estar no futuro.',
            ]);

            foreach ($validator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $errors[] = [
                        'field' => $field,
                        'message' => $message,
                        'value' => $field === 'cpf' ? $this->maskCpf($payload['cpf']) : $this->safeErrorValue($field, $payload[$field] ?? null),
                    ];
                }
            }

            if ($payload['cpf'] !== null) {
                if (isset($seenCpfs[$payload['cpf']])) {
                    $errors[] = ['field' => 'cpf', 'message' => 'O CPF aparece mais de uma vez no arquivo.', 'value' => $this->maskCpf($payload['cpf'])];
                }
                $seenCpfs[$payload['cpf']] = true;
            }

            $postalCode = $payload['postal_code'];
            if (is_string($postalCode) && strlen($postalCode) === 8) {
                $address = $postalCodeResults[$postalCode] ?? null;
                if ($address === null) {
                    $errors[] = ['field' => 'cep', 'message' => 'O CEP não foi encontrado.', 'value' => $this->maskPostalCode($postalCode)];
                } else {
                    $city = City::query()->where('ibge_code', $address['ibge_code'])->first();
                    if ($city === null) {
                        $errors[] = ['field' => 'cep', 'message' => 'A cidade do CEP não está cadastrada no Genesis+.', 'value' => $this->maskPostalCode($postalCode)];
                    } else {
                        $payload['city_id'] = $city->id;
                        $payload['street'] = $address['street'] !== '' ? $address['street'] : $member?->street;
                        $payload['neighborhood'] = $address['neighborhood'] !== '' ? $address['neighborhood'] : $member?->neighborhood;
                    }
                }
            } elseif ($row['operation'] === MemberImportOperation::Update) {
                unset($payload['postal_code'], $payload['number'], $payload['complement']);
            }

            if ($row['operation'] === MemberImportOperation::Update && $payload['cpf'] === null) {
                unset($payload['cpf']);
            }

            $result[] = [
                'row_number' => $row['row_number'],
                'operation' => $row['operation'],
                'member_id' => $member?->id ?? $row['member_id'],
                'normalized_payload' => $payload,
                'errors' => $errors,
                'status' => $errors === [] ? MemberImportRowStatus::Valid : MemberImportRowStatus::Invalid,
            ];
        }

        return $result;
    }

    /** @param list<array{row_number: int, operation: MemberImportOperation, member_id: string|null, normalized_payload: array<string, mixed>, errors: list<array{field: string, message: string, value?: string|null}>, status: MemberImportRowStatus}> $rows */
    private function persistValidation(MemberImport $import, array $rows): void
    {
        $errorsCount = collect($rows)->where('status', MemberImportRowStatus::Invalid)->count();
        $newCount = collect($rows)->where('operation', MemberImportOperation::Create)->where('status', MemberImportRowStatus::Valid)->count();
        $updatesCount = collect($rows)->where('operation', MemberImportOperation::Update)->where('status', MemberImportRowStatus::Valid)->count();

        DB::transaction(function () use ($import, $rows, $errorsCount, $newCount, $updatesCount): void {
            $locked = MemberImport::query()->lockForUpdate()->findOrFail($import->id);
            $locked->rows()->delete();

            foreach (array_chunk($rows, 250) as $chunk) {
                $locked->rows()->createMany($chunk);
            }

            $locked->update([
                'total_rows' => count($rows),
                'new_members_count' => $newCount,
                'updates_count' => $updatesCount,
                'errors_count' => $errorsCount,
                'status' => $errorsCount > 0 ? MemberImportStatus::ValidationFailed : MemberImportStatus::AwaitingConfirmation,
                'validated_at' => now(),
                'failed_at' => $errorsCount > 0 ? now() : null,
                'failure_reason' => $errorsCount > 0 ? 'O arquivo contém linhas inválidas e não pode ser confirmado.' : null,
            ]);
        });

        $import->refresh();
        $action = $errorsCount > 0 ? 'member_import.validation_failed' : 'member_import.validation_completed';
        $this->audit->record($action, 'member_imports', $import, 'church', $import->church_id, $this->auditDetails($import), $import->uploadedBy);
    }

    private function persistStructuralFailure(MemberImport $import, MemberImportValidationException $exception): void
    {
        DB::transaction(function () use ($import, $exception): void {
            $locked = MemberImport::query()->lockForUpdate()->findOrFail($import->id);
            $locked->rows()->delete();
            $locked->rows()->create([
                'row_number' => 1,
                'operation' => MemberImportOperation::Create,
                'normalized_payload' => null,
                'errors' => $exception->errors !== [] ? $exception->errors : [['field' => 'planilha', 'message' => $exception->getMessage(), 'value' => null]],
                'status' => MemberImportRowStatus::Invalid,
            ]);
            $locked->update([
                'total_rows' => 0,
                'new_members_count' => 0,
                'updates_count' => 0,
                'errors_count' => 1,
                'status' => MemberImportStatus::ValidationFailed,
                'validated_at' => now(),
                'failed_at' => now(),
                'failure_reason' => Str::limit($exception->getMessage(), 1000),
            ]);
        });

        $import->refresh();
        $this->audit->record('member_import.validation_failed', 'member_imports', $import, 'church', $import->church_id, $this->auditDetails($import), $import->uploadedBy);
    }

    private function normalizeDate(mixed $value): string|false|null
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches) !== 1 || ! checkdate((int) $matches[2], (int) $matches[1], (int) $matches[3])) {
            return false;
        }

        return CarbonImmutable::create((int) $matches[3], (int) $matches[2], (int) $matches[1])->format('Y-m-d');
    }

    private function normalizeSex(mixed $value): ?string
    {
        return match (Str::lower(trim((string) ($value ?? '')))) {
            '' => null,
            'm', 'male', 'masculino' => Sex::Male->value,
            'f', 'female', 'feminino' => Sex::Female->value,
            default => trim((string) $value),
        };
    }

    private function digits(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) ($value ?? '')) ?? '';

        return $digits !== '' ? $digits : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = Str::squish((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function maskCpf(?string $cpf): ?string
    {
        return $cpf === null || strlen($cpf) < 4 ? null : '***.***.***-'.substr($cpf, -2);
    }

    private function maskPostalCode(string $postalCode): string
    {
        return substr($postalCode, 0, 2).'***-***';
    }

    private function safeErrorValue(string $field, mixed $value): ?string
    {
        if ($value === null || in_array($field, ['email', 'phone'], true)) {
            return null;
        }

        return Str::limit((string) $value, 80);
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
