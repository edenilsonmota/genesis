<?php

use App\Enums\MemberImportStatus;
use App\Jobs\ProcessMemberImportJob;
use App\Jobs\ValidateMemberImportJob;
use App\Models\Area;
use App\Models\Church;
use App\Models\City;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\MemberImport;
use App\Models\State;
use App\Models\User;
use App\PermissionLevel;
use App\Services\MemberImportProcessingService;
use App\Services\MemberImportSpreadsheetService;
use App\Services\MemberImportValidationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function memberImportChurch(): array
{
    $area = Area::factory()->create();
    $state = State::factory()->create(['ibge_code' => 35, 'abbreviation' => 'SP']);
    $city = City::factory()->for($state)->create(['ibge_code' => 3550308, 'name' => 'São Paulo']);
    $church = Church::factory()->for($area)->for($city)->create();

    return compact('area', 'state', 'city', 'church');
}

/** @param list<list<mixed>> $rows */
function memberImportUpload(array $rows, array $headers = MemberImportSpreadsheetService::HEADERS, string $sheetName = 'Membros'): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet()->setTitle($sheetName);
    $sheet->fromArray([$headers, ...$rows]);
    $path = tempnam(sys_get_temp_dir(), 'member-import-test-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();

    return new UploadedFile($path, 'membros.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function validMemberImportRow(array $overrides = []): array
{
    $row = [null, 'Maria da Silva', '529.982.247-25', 'maria@example.com', '(11) 99999-9999', '20/05/1990', 'F', '01310-100', '100', 'Apto. 1'];
    foreach ($overrides as $index => $value) {
        $row[$index] = $value;
    }

    return $row;
}

function fakePostalCode(): void
{
    Http::fake(['viacep.com.br/*' => Http::response([
        'ibge' => '3550308',
        'logradouro' => 'Avenida Paulista',
        'bairro' => 'Bela Vista',
        'complemento' => '',
    ])]);
}

it('uses the dedicated Redis queue with bounded retries and timeouts', function () {
    $validation = new ValidateMemberImportJob('import-id');
    $processing = new ProcessMemberImportJob('import-id');

    expect($validation->connection)->toBe('redis')
        ->and($validation->queue)->toBe('imports')
        ->and($validation->tries)->toBe(3)
        ->and($validation->timeout)->toBe(300)
        ->and($validation->failOnTimeout)->toBeTrue()
        ->and($processing->connection)->toBe('redis')
        ->and($processing->queue)->toBe('imports')
        ->and($processing->tries)->toBe(3)
        ->and($processing->timeout)->toBe(600)
        ->and($processing->failOnTimeout)->toBeTrue();
});

it('persists a safe failure state after queue retries are exhausted', function () {
    ['church' => $church] = memberImportChurch();
    $user = User::factory()->create();
    $import = MemberImport::factory()->for($church)->for($user, 'uploadedBy')->create([
        'status' => MemberImportStatus::Processing,
    ]);

    (new ProcessMemberImportJob($import->id))->failed(new RuntimeException('database detail that must not be exposed'));

    expect($import->refresh()->status)->toBe(MemberImportStatus::ProcessingFailed)
        ->and($import->failure_reason)->toContain('sem aplicar alterações parciais')
        ->and($import->failure_reason)->not->toContain('database detail');
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'member_import.processing_failed',
        'record_id' => $import->id,
        'scope_id' => $church->id,
    ]);
});

it('allows readers to inspect and download templates but only writers can upload', function () {
    ['church' => $church] = memberImportChurch();
    $reader = userWithPermission('members.import', PermissionLevel::Read, $church);

    $this->actingAs($reader)->get(route('members-import.index'))->assertOk();
    $this->actingAs($reader)->get(route('members-import.templates.blank', ['church_id' => $church->id]))->assertDownload('modelo-novos-membros.xlsx');
    $this->actingAs($reader)->post(route('members-import.store'), [
        'church_id' => $church->id,
        'file' => memberImportUpload([validMemberImportRow()]),
    ])->assertForbidden();
});

it('blocks a manually supplied church outside the user scope', function () {
    Storage::fake('local');
    Queue::fake();
    ['city' => $city, 'church' => $church] = memberImportChurch();
    $otherChurch = Church::factory()->for($church->area)->for($city)->create();
    $writer = userWithPermission('members.import', PermissionLevel::Write, $church);

    $this->actingAs($writer)->post(route('members-import.store'), [
        'church_id' => $otherChurch->id,
        'file' => memberImportUpload([validMemberImportRow()]),
    ])->assertForbidden();

    expect(MemberImport::query()->count())->toBe(0);
});

it('rejects non XLSX uploads before storing or dispatching them', function () {
    Storage::fake('local');
    Queue::fake();
    ['church' => $church] = memberImportChurch();
    $writer = userWithPermission('members.import', PermissionLevel::Write, $church);

    $this->actingAs($writer)->post(route('members-import.store'), [
        'church_id' => $church->id,
        'file' => UploadedFile::fake()->create('membros.csv', 20, 'text/csv'),
    ])->assertSessionHasErrors('file');

    expect(MemberImport::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('stores the XLSX privately and dispatches validation outside the request', function () {
    Storage::fake('local');
    Queue::fake();
    ['church' => $church] = memberImportChurch();
    $writer = userWithPermission('members.import', PermissionLevel::Write, $church);

    $this->actingAs($writer)->post(route('members-import.store'), [
        'church_id' => $church->id,
        'file' => memberImportUpload([validMemberImportRow()]),
    ])->assertRedirect();

    $import = MemberImport::query()->sole();
    Storage::disk('local')->assertExists($import->file_path);
    expect($import->status)->toBe(MemberImportStatus::Uploaded)
        ->and(str_starts_with($import->file_path, 'member-imports/'))->toBeTrue();
    Queue::assertPushed(ValidateMemberImportJob::class, fn (ValidateMemberImportJob $job): bool => $job->memberImportId === $import->id && $job->connection === 'redis' && $job->queue === 'imports');
});

it('validates without changing members and then creates atomically after confirmation', function () {
    Storage::fake('local');
    Queue::fake();
    fakePostalCode();
    ['church' => $church] = memberImportChurch();
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->post(route('members-import.store'), [
        'church_id' => $church->id,
        'file' => memberImportUpload([validMemberImportRow()]),
    ]);
    $import = MemberImport::query()->sole();

    app(MemberImportValidationService::class)->validate($import);
    expect(Member::query()->count())->toBe(0)
        ->and($import->refresh()->status)->toBe(MemberImportStatus::AwaitingConfirmation)
        ->and($import->new_members_count)->toBe(1)
        ->and($import->errors_count)->toBe(0);

    $this->actingAs($administrator)->post(route('members-import.confirm', $import))->assertRedirect();
    Queue::assertPushed(ProcessMemberImportJob::class);
    app(MemberImportProcessingService::class)->process($import->refresh());

    $member = Member::query()->sole();
    expect($member->cpf)->toBe('52998224725')
        ->and($member->phone)->toBe('11999999999')
        ->and($member->postal_code)->toBe('01310100')
        ->and($member->street)->toBe('Avenida Paulista')
        ->and($member->city_id)->toBe($church->city_id)
        ->and($member->user)->toBeNull()
        ->and($import->refresh()->status)->toBe(MemberImportStatus::Completed);
    expect(MemberChurchMembership::query()->where('member_id', $member->id)->where('church_id', $church->id)->exists())->toBeTrue();
});

it('blocks the whole file when a row is invalid and masks CPF errors', function () {
    Storage::fake('local');
    Queue::fake();
    fakePostalCode();
    ['church' => $church] = memberImportChurch();
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->post(route('members-import.store'), [
        'church_id' => $church->id,
        'file' => memberImportUpload([validMemberImportRow(), validMemberImportRow([1 => '', 2 => '111.111.111-11'])]),
    ]);
    $import = MemberImport::query()->sole();
    app(MemberImportValidationService::class)->validate($import);

    expect($import->refresh()->status)->toBe(MemberImportStatus::ValidationFailed)
        ->and($import->errors_count)->toBe(1)
        ->and(Member::query()->count())->toBe(0);
    $serializedErrors = json_encode($import->rows()->whereNotNull('errors')->pluck('errors')->all());
    expect($serializedErrors)->not->toContain('11111111111')->toContain('***.***.***-11');
    $this->actingAs($administrator)->get(route('members-import.show', $import))
        ->assertOk()
        ->assertSee('A importação inteira está bloqueada')
        ->assertDontSee('11111111111');
    $this->actingAs($administrator)->post(route('members-import.confirm', $import))->assertSessionHasErrors('import');
});

it('detects duplicate CPFs inside the same file', function () {
    Storage::fake('local');
    Queue::fake();
    fakePostalCode();
    ['church' => $church] = memberImportChurch();
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->post(route('members-import.store'), [
        'church_id' => $church->id,
        'file' => memberImportUpload([
            validMemberImportRow(),
            validMemberImportRow([1 => 'Outra pessoa', 3 => 'outra@example.com']),
        ]),
    ]);
    $import = MemberImport::query()->sole();
    app(MemberImportValidationService::class)->validate($import);

    expect($import->refresh()->status)->toBe(MemberImportStatus::ValidationFailed)
        ->and(json_encode($import->rows()->where('row_number', 3)->value('errors'), JSON_UNESCAPED_UNICODE))->toContain('aparece mais de uma vez');
});

it('rejects the wrong sheet and exact header violations in the validation job', function () {
    Storage::fake('local');
    Queue::fake();
    ['church' => $church] = memberImportChurch();
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->post(route('members-import.store'), [
        'church_id' => $church->id,
        'file' => memberImportUpload([validMemberImportRow()], sheetName: 'Dados'),
    ]);
    $missingSheet = MemberImport::query()->sole();
    app(MemberImportValidationService::class)->validate($missingSheet);
    expect($missingSheet->refresh()->status)->toBe(MemberImportStatus::ValidationFailed)
        ->and($missingSheet->failure_reason)->toContain('Membros');

    $this->actingAs($administrator)->post(route('members-import.store'), [
        'church_id' => $church->id,
        'file' => memberImportUpload([validMemberImportRow()], headers: ['nome', ...array_slice(MemberImportSpreadsheetService::HEADERS, 1)]),
    ]);
    $wrongHeader = MemberImport::query()->whereKeyNot($missingSheet->id)->sole();
    app(MemberImportValidationService::class)->validate($wrongHeader);
    expect($wrongHeader->refresh()->status)->toBe(MemberImportStatus::ValidationFailed)
        ->and($wrongHeader->failure_reason)->toContain('cabeçalhos');
});

it('rejects formulas and duplicate identifiers without executing spreadsheet code', function () {
    Storage::fake('local');
    Queue::fake();
    fakePostalCode();
    ['church' => $church] = memberImportChurch();
    $administrator = User::factory()->globalAdministrator()->create();
    $file = memberImportUpload([validMemberImportRow()]);
    $spreadsheet = IOFactory::load($file->getPathname());
    $spreadsheet->getActiveSheet()->setCellValueExplicit('B2', '=1+1', DataType::TYPE_FORMULA);
    (new Xlsx($spreadsheet))->save($file->getPathname());

    $this->actingAs($administrator)->post(route('members-import.store'), ['church_id' => $church->id, 'file' => $file]);
    $import = MemberImport::query()->sole();
    app(MemberImportValidationService::class)->validate($import);

    expect($import->refresh()->status)->toBe(MemberImportStatus::ValidationFailed)
        ->and(json_encode($import->rows()->firstOrFail()->errors, JSON_UNESCAPED_UNICODE))->toContain('Fórmulas não são permitidas');
});

it('updates only an active member from the selected church and preserves CPF and address when blank', function () {
    Storage::fake('local');
    Queue::fake();
    ['city' => $city, 'church' => $church] = memberImportChurch();
    $member = Member::factory()->for($city)->create([
        'name' => 'Nome anterior',
        'cpf' => '52998224725',
        'postal_code' => '01310100',
        'street' => 'Rua preservada',
    ]);
    MemberChurchMembership::factory()->for($member)->for($church)->create();
    $administrator = User::factory()->globalAdministrator()->create();
    $row = [$member->id, 'Nome atualizado', '', '', '', '', '', '', '', ''];

    $this->actingAs($administrator)->post(route('members-import.store'), [
        'church_id' => $church->id,
        'file' => memberImportUpload([$row]),
    ]);
    $import = MemberImport::query()->sole();
    app(MemberImportValidationService::class)->validate($import);
    $this->actingAs($administrator)->post(route('members-import.confirm', $import));
    app(MemberImportProcessingService::class)->process($import->refresh());

    expect($member->refresh()->name)->toBe('Nome atualizado')
        ->and($member->cpf)->toBe('52998224725')
        ->and($member->postal_code)->toBe('01310100')
        ->and($member->street)->toBe('Rua preservada')
        ->and($member->memberships()->count())->toBe(1);
});

it('exports only active members from the selected church and protects formula injection', function () {
    ['city' => $city, 'church' => $church] = memberImportChurch();
    $otherChurch = Church::factory()->for($church->area)->for($city)->create();
    $visible = Member::factory()->for($city)->create(['name' => '=PERIGOSO']);
    $hidden = Member::factory()->for($city)->create();
    MemberChurchMembership::factory()->for($visible)->for($church)->create();
    MemberChurchMembership::factory()->for($hidden)->for($otherChurch)->create();

    $spreadsheet = app(MemberImportSpreadsheetService::class)->updateTemplate($church);
    $sheet = $spreadsheet->getSheetByName('Membros');

    expect($sheet->getHighestDataRow())->toBe(2)
        ->and($sheet->getCell('A2')->getValue())->toBe($visible->id)
        ->and($sheet->getCell('B2')->getValue())->toBe("'=PERIGOSO")
        ->and($sheet->getCell('B2')->getDataType())->toBe(DataType::TYPE_STRING);
});
