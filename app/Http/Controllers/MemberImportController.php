<?php

namespace App\Http\Controllers;

use App\Enums\MemberImportRowStatus;
use App\Http\Requests\MemberImport\SelectMemberImportChurchRequest;
use App\Http\Requests\MemberImport\StoreMemberImportRequest;
use App\Models\Church;
use App\Models\MemberImport;
use App\PermissionLevel;
use App\Services\AuditService;
use App\Services\MemberImportService;
use App\Services\MemberImportSpreadsheetService;
use App\Services\PermissionService;
use App\Status;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberImportController extends Controller
{
    public function index(Request $request, PermissionService $permissions): View
    {
        Gate::authorize('viewAny', MemberImport::class);
        $churches = $permissions->churchesWithPermission($request->user(), 'members.import', PermissionLevel::Read);
        $selectedChurchId = $request->string('church_id')->toString();
        if (! $churches->contains('id', $selectedChurchId)) {
            $selectedChurchId = $permissions->currentChurch($request->user())?->id ?? '';
        }
        $selectedChurch = $churches->firstWhere('id', $selectedChurchId);

        $imports = MemberImport::query()
            ->with(['church:id,name', 'uploadedBy:id,display_name'])
            ->whereIn('church_id', $churches->pluck('id'))
            ->when($selectedChurchId !== '', fn (Builder $query): Builder => $query->where('church_id', $selectedChurchId))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('members-import.index', [
            'churches' => $churches,
            'selectedChurchId' => $selectedChurchId,
            'imports' => $imports,
            'canWrite' => $selectedChurch !== null && $permissions->can($request->user(), 'members.import', PermissionLevel::Write, $selectedChurch),
            'canExportMembers' => $selectedChurch !== null && $permissions->can($request->user(), 'members', PermissionLevel::Read, $selectedChurch),
        ]);
    }

    public function store(StoreMemberImportRequest $request, MemberImportService $service, PermissionService $permissions): RedirectResponse
    {
        $church = Church::query()->where('status', Status::Active)->findOrFail($request->validated('church_id'));
        Gate::authorize('createForChurch', [MemberImport::class, $church]);
        abort_unless($permissions->canAccessChurch($request->user(), $church), 404);
        $import = $service->upload($request->file('file'), $church, $request->user());

        return redirect()->route('members-import.show', $import)
            ->with('success', 'Arquivo enviado. A validação será executada em segundo plano.');
    }

    public function show(MemberImport $memberImport): View
    {
        Gate::authorize('view', $memberImport);
        $memberImport->load(['church:id,name', 'uploadedBy:id,display_name', 'confirmedBy:id,display_name']);
        $rows = $memberImport->rows()
            ->where('status', MemberImportRowStatus::Invalid->value)
            ->orderBy('row_number')
            ->paginate(25);

        return view('members-import.show', compact('memberImport', 'rows'));
    }

    public function blankTemplate(SelectMemberImportChurchRequest $request, MemberImportSpreadsheetService $spreadsheets, AuditService $audit, PermissionService $permissions): BinaryFileResponse
    {
        $church = Church::query()->where('status', Status::Active)->findOrFail($request->validated('church_id'));
        Gate::authorize('viewForChurch', [MemberImport::class, $church]);
        $audit->record('member_import.blank_template_generated', 'member_imports', scopeType: 'church', scopeId: $church->id, details: ['church_id' => $church->id]);
        $path = $spreadsheets->writeToTemporaryFile($spreadsheets->blankTemplate());

        return response()->download($path, 'modelo-novos-membros.xlsx')->deleteFileAfterSend();
    }

    public function updateTemplate(SelectMemberImportChurchRequest $request, MemberImportSpreadsheetService $spreadsheets, AuditService $audit): BinaryFileResponse
    {
        $church = Church::query()->where('status', Status::Active)->findOrFail($request->validated('church_id'));
        Gate::authorize('exportForChurch', [MemberImport::class, $church]);
        $audit->record('member_import.update_template_exported', 'member_imports', scopeType: 'church', scopeId: $church->id, details: ['church_id' => $church->id]);
        $path = $spreadsheets->writeToTemporaryFile($spreadsheets->updateTemplate($church));

        return response()->download($path, 'membros-'.Str::slug($church->name).'-atualizacao.xlsx')->deleteFileAfterSend();
    }

    public function original(MemberImport $memberImport): StreamedResponse
    {
        Gate::authorize('download', $memberImport);
        abort_unless(Storage::disk('local')->exists($memberImport->file_path), 404);

        return Storage::disk('local')->download($memberImport->file_path, $memberImport->original_filename);
    }

    public function errorReport(MemberImport $memberImport, MemberImportSpreadsheetService $spreadsheets): BinaryFileResponse
    {
        Gate::authorize('download', $memberImport);
        abort_unless($memberImport->errors_count > 0, 404);
        $rows = $memberImport->rows()->where('status', MemberImportRowStatus::Invalid->value)->orderBy('row_number')->get();
        $path = $spreadsheets->writeToTemporaryFile($spreadsheets->withErrorReport($memberImport->file_path, $rows));

        return response()->download($path, 'erros-'.$memberImport->original_filename)->deleteFileAfterSend();
    }

    public function confirm(MemberImport $memberImport, MemberImportService $service): RedirectResponse
    {
        Gate::authorize('confirm', $memberImport);
        $service->confirm($memberImport, request()->user());

        return redirect()->route('members-import.show', $memberImport)
            ->with('success', 'Importação confirmada. O processamento será executado em segundo plano.');
    }
}
