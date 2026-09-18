<?php

namespace App\Services;

use App\Enums\Sex;
use App\Exceptions\MemberImportValidationException;
use App\Models\Church;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class MemberImportSpreadsheetService
{
    public const MAX_ROWS = 5000;

    public const HEADERS = [
        'id_membro',
        'nome',
        'cpf',
        'email',
        'telefone',
        'data_nascimento',
        'sexo',
        'cep',
        'numero',
        'complemento',
    ];

    public function blankTemplate(): Spreadsheet
    {
        $spreadsheet = $this->baseSpreadsheet();
        $instructions = $spreadsheet->createSheet()->setTitle('Instruções');
        $instructions->fromArray([
            ['Importação de membros — Genesis+'],
            ['Preencha somente a aba Membros e não altere os cabeçalhos.'],
            ['Para novos membros, deixe id_membro vazio. Nome, CPF e CEP são obrigatórios porque o cadastro atual exige cidade.'],
            ['Sexo: use M ou F. Data de nascimento: dd/mm/aaaa.'],
            ['CPF, telefone e CEP podem conter máscara; o sistema removerá a formatação.'],
            ['Exemplo fictício:', 'Maria de Exemplo', '529.982.247-25', 'maria@example.com', '(11) 99999-9999', '20/05/1990', 'F', '01310-100', '100', 'Apto. 1'],
        ]);
        $instructions->getColumnDimension('A')->setWidth(95);
        $instructions->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        return $spreadsheet;
    }

    public function updateTemplate(Church $church): Spreadsheet
    {
        $spreadsheet = $this->baseSpreadsheet();
        $sheet = $spreadsheet->getSheetByName('Membros');
        $row = 2;

        Member::query()
            ->whereHas('memberships', fn (Builder $query): Builder => $query
                ->effectiveOn(today()->toDateString())
                ->where('church_id', $church->id))
            ->orderBy('name')
            ->chunkById(250, function ($members) use ($sheet, &$row): void {
                foreach ($members as $member) {
                    $values = [
                        $member->id,
                        $member->name,
                        $member->cpf,
                        $member->email,
                        $member->phone,
                        $member->birth_date?->format('d/m/Y'),
                        match ($member->sex) {
                            Sex::Male => 'M',
                            Sex::Female => 'F',
                            default => null,
                        },
                        $member->postal_code,
                        $member->number,
                        $member->complement,
                    ];

                    foreach ($values as $column => $value) {
                        $sheet->setCellValueExplicit([$column + 1, $row], $this->safeExportValue($value), DataType::TYPE_STRING);
                    }

                    $row++;
                }
            });

        if ($row > 2) {
            $sheet->getStyle('A2:A'.($row - 1))->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE6F2FF');
        }

        $instructions = $spreadsheet->createSheet()->setTitle('Instruções');
        $instructions->fromArray([
            ['Atualização de membros — '.$this->safeExportValue($church->name)],
            ['Não altere id_membro. Ele identifica o cadastro e será validado novamente pelo servidor.'],
            ['Não é possível mover membros, alterar vínculos, cargos, usuários ou permissões por esta planilha.'],
            ['CPF vazio mantém o CPF atual. CEP vazio mantém o endereço atual.'],
        ]);
        $instructions->getColumnDimension('A')->setWidth(105);
        $instructions->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        return $spreadsheet;
    }

    /**
     * @return list<array{row_number: int, values: array<string, mixed>, formula_fields: list<string>}>
     */
    public function rows(string $filePath): array
    {
        $absolutePath = Storage::disk('local')->path($filePath);

        try {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx;
            $reader->setReadDataOnly(false);
            $reader->setReadEmptyCells(false);

            if (! $reader->canRead($absolutePath)) {
                throw new MemberImportValidationException('O arquivo não é uma planilha XLSX válida ou está protegido.');
            }

            $spreadsheet = $reader->load($absolutePath);
        } catch (MemberImportValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new MemberImportValidationException('Não foi possível abrir o arquivo XLSX. Verifique se ele não está corrompido ou protegido por senha.', previous: $exception);
        }

        $sheet = $spreadsheet->getSheetByName('Membros');
        if (! $sheet instanceof Worksheet) {
            $spreadsheet->disconnectWorksheets();
            throw new MemberImportValidationException('A aba obrigatória “Membros” não foi encontrada.');
        }

        $headers = [];
        for ($column = 1; $column <= count(self::HEADERS); $column++) {
            $headers[] = trim((string) $sheet->getCell([$column, 1])->getValue());
        }

        if ($headers !== self::HEADERS || $sheet->getHighestDataColumn(1) !== 'J') {
            $spreadsheet->disconnectWorksheets();
            throw new MemberImportValidationException('Os cabeçalhos da aba Membros estão ausentes, duplicados, renomeados ou fora da ordem esperada.');
        }

        $rows = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $values = [];
            $formulaFields = [];
            $hasValue = false;

            foreach (self::HEADERS as $index => $header) {
                $cell = $sheet->getCell([$index + 1, $rowNumber]);
                $value = $cell->getValue();
                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    $formulaFields[] = $header;
                    $value = null;
                }

                if ($header === 'data_nascimento' && is_numeric($value) && ExcelDate::isDateTime($cell)) {
                    $value = ExcelDate::excelToDateTimeObject((float) $value)->format('d/m/Y');
                }

                $values[$header] = $value;
                $hasValue = $hasValue || (is_scalar($value) && trim((string) $value) !== '');
            }

            if (! $hasValue && $formulaFields === []) {
                continue;
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'values' => $values,
                'formula_fields' => $formulaFields,
            ];
            if (count($rows) > self::MAX_ROWS) {
                $spreadsheet->disconnectWorksheets();
                throw new MemberImportValidationException('O arquivo excede o limite de '.self::MAX_ROWS.' linhas preenchidas.');
            }
        }

        $spreadsheet->disconnectWorksheets();

        if ($rows === []) {
            throw new MemberImportValidationException('A aba Membros não possui linhas preenchidas para importação.');
        }

        return $rows;
    }

    public function withErrorReport(string $filePath, iterable $rows): Spreadsheet
    {
        $absolutePath = Storage::disk('local')->path($filePath);
        try {
            $spreadsheet = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx)->load($absolutePath);
        } catch (Throwable $exception) {
            throw new RuntimeException('Não foi possível gerar a cópia com os erros.', previous: $exception);
        }

        if (($existing = $spreadsheet->getSheetByName('Erros de importação')) !== null) {
            $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($existing));
        }

        foreach ($spreadsheet->getWorksheetIterator() as $sourceSheet) {
            foreach ($sourceSheet->getRowIterator() as $sourceRow) {
                foreach ($sourceRow->getCellIterator() as $cell) {
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $cell->setValueExplicit("'".(string) $cell->getValue(), DataType::TYPE_STRING);
                    }
                }
            }
        }

        $sheet = $spreadsheet->createSheet()->setTitle('Erros de importação');
        $sheet->fromArray([['linha', 'campo', 'mensagem', 'valor']]);
        $outputRow = 2;

        foreach ($rows as $row) {
            foreach ($row->errors ?? [] as $error) {
                $sheet->fromArray([[
                    $row->row_number,
                    $error['field'] ?? 'linha',
                    $this->safeExportValue($error['message'] ?? 'Erro de validação.'),
                    $this->safeExportValue($error['value'] ?? null),
                ]], null, "A{$outputRow}");
                $outputRow++;
            }
        }

        $this->styleHeader($sheet);
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(70);
        $sheet->getColumnDimension('D')->setWidth(30);

        return $spreadsheet;
    }

    public function writeToTemporaryFile(Spreadsheet $spreadsheet): string
    {
        $path = tempnam(sys_get_temp_dir(), 'genesis-members-');
        if ($path === false) {
            throw new RuntimeException('Não foi possível criar o arquivo temporário.');
        }

        $xlsxPath = $path.'.xlsx';
        @unlink($path);
        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        return $xlsxPath;
    }

    private function baseSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Membros');
        $sheet->fromArray([self::HEADERS]);
        $this->styleHeader($sheet);

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setWidth(match ($column) {
                'A' => 38,
                'B', 'D', 'J' => 32,
                default => 20,
            });
        }
        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    private function styleHeader(Worksheet $sheet): void
    {
        $lastColumn = $sheet->getHighestDataColumn(1);
        $style = $sheet->getStyle("A1:{$lastColumn}1");
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0051F5');
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function safeExportValue(mixed $value): string
    {
        $value = $value === null ? '' : (string) $value;

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
