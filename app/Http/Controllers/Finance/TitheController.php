<?php

namespace App\Http\Controllers\Finance;

use App\Enums\FinancialCategoryType;
use App\Enums\FinancialTransactionOrigin;
use App\Enums\FinancialTransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\Tithe\StoreTitheRequest;
use App\Http\Requests\Finance\Tithe\UpdateTitheRequest;
use App\Models\Church;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\PermissionLevel;
use App\Services\Finance\FinancialTransactionService;
use App\Services\PermissionService;
use App\Status;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TitheController extends Controller
{
    public function index(Request $request, PermissionService $permissions): View
    {
        $church = $this->churchForTithes($request, $permissions);
        abort_unless($church !== null && $permissions->can($request->user(), 'finance.tithes', PermissionLevel::Read, $church), 403);
        $referenceMonth = $this->referenceMonth($request);
        $canWrite = $permissions->can($request->user(), 'finance.tithes', PermissionLevel::Write, $church);

        $members = Member::query()
            ->where('status', Status::Active->value)
            ->whereHas('memberships', fn (Builder $query): Builder => $query
                ->effectiveOn(today()->toDateString())
                ->where('church_id', $church->id))
            ->with([
                'user:id,member_id,display_name,username',
                'memberships' => fn ($query) => $query
                    ->where('church_id', $church->id)
                    ->effectiveOn(today()->toDateString()),
                'financialTransactions' => fn ($query) => $query
                    ->where('origin', FinancialTransactionOrigin::Tithe->value)
                    ->where('status', FinancialTransactionStatus::Settled->value)
                    ->whereNull('reversed_at')
                    ->whereDate('competence_month', $referenceMonth->toDateString())
                    ->whereHas('movements.account', fn (Builder $query): Builder => $query->where('church_id', $church->id))
                    ->latest('occurred_on'),
            ])
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('finance.tithes.index', compact('church', 'referenceMonth', 'members', 'canWrite'));
    }

    public function store(StoreTitheRequest $request, PermissionService $permissions, FinancialTransactionService $transactions): RedirectResponse
    {
        $data = $request->validated();
        $church = $this->churchForTithes($request, $permissions);
        abort_unless(
            $church !== null
            && $data['church_id'] === $church->id
            && $permissions->can($request->user(), 'finance.tithes', PermissionLevel::Write, $church),
            403,
        );

        $member = $this->membersForChurch($church)->firstWhere('id', $data['member_id']);
        if ($member === null) {
            throw ValidationException::withMessages(['member_id' => 'Selecione um membro ativo vinculado à igreja escolhida.']);
        }

        $account = FinancialAccount::query()->active()->where('church_id', $church->id)->where('is_default', true)->first();
        $category = FinancialCategory::query()
            ->where('area_id', $church->area_id)
            ->where('type', FinancialCategoryType::Income->value)
            ->whereRaw('LOWER(name) = LOWER(?)', ['Dízimos'])
            ->first();
        if ($account === null || $category === null) {
            throw ValidationException::withMessages(['church_id' => 'A igreja precisa ter caixa padrão e categoria Dízimos ativos.']);
        }

        $transactions->createTithe([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'member_id' => $member->id,
            'title' => 'Dízimo · '.$member->name,
            'description' => $data['description'],
            'amount' => $data['amount'],
            'occurred_on' => $data['paid_on'],
            'competence_month' => $data['competence_month'],
            'payment_method' => $data['payment_method'],
            'status' => FinancialTransactionStatus::Settled,
        ], $request->user());

        return redirect()->route('finance.tithes.index', [
            'reference_month' => (int) substr($data['competence_month'], 5, 2),
            'reference_year' => (int) substr($data['competence_month'], 0, 4),
        ])
            ->with('success', "Dízimo de {$member->name} registrado com sucesso.");
    }

    public function show(FinancialTransaction $financialTransaction, PermissionService $permissions): View
    {
        $church = $this->churchForTithes(request(), $permissions);
        abort_unless(
            $church !== null
            && $financialTransaction->origin === FinancialTransactionOrigin::Tithe
            && $permissions->can(request()->user(), 'finance.tithes', PermissionLevel::Read, $church)
            && $financialTransaction->movements()->whereHas('account', fn (Builder $query): Builder => $query->where('church_id', $church->id))->exists(),
            404,
        );

        $financialTransaction->load(['member.user', 'category', 'movements.account.church', 'createdByUser']);

        return view('finance.tithes.show', compact('financialTransaction', 'church'));
    }

    public function updateDetails(
        UpdateTitheRequest $request,
        FinancialTransaction $financialTransaction,
        PermissionService $permissions,
        FinancialTransactionService $transactions,
    ): RedirectResponse {
        $church = $this->churchForTithes($request, $permissions);
        abort_unless(
            $church !== null
            && $financialTransaction->origin === FinancialTransactionOrigin::Tithe
            && $permissions->can($request->user(), 'finance.tithes', PermissionLevel::Write, $church)
            && $financialTransaction->movements()->whereHas('account', fn (Builder $query): Builder => $query->where('church_id', $church->id))->exists(),
            404,
        );

        $transactions->updateTitheDetails($financialTransaction, $request->validated(), $request->user());

        return back()->with('success', 'Detalhes do dízimo atualizados com sucesso.');
    }

    public function reverse(Request $request, FinancialTransaction $financialTransaction, PermissionService $permissions, FinancialTransactionService $transactions): RedirectResponse
    {
        $church = $this->churchForTithes($request, $permissions);
        abort_unless($church !== null && $financialTransaction->origin === FinancialTransactionOrigin::Tithe && $permissions->can($request->user(), 'finance.tithes', PermissionLevel::Write, $church) && $financialTransaction->movements()->whereHas('account', fn (Builder $query): Builder => $query->where('church_id', $church->id))->exists(), 404);
        $transactions->reverse($financialTransaction, 'Dízimo removido pela tela de dízimos.', $request->user(), 'finance.tithes');

        return redirect()->route('finance.tithes.index')->with('success', 'Dízimo apagado por estorno; o histórico foi preservado.');
    }

    /** @return Collection<int, Member> */
    private function membersForChurch(Church $church): Collection
    {
        return Member::query()->where('status', Status::Active->value)
            ->whereHas('memberships', fn (Builder $query): Builder => $query->effectiveOn(today()->toDateString())->where('church_id', $church->id))
            ->orderBy('name')->get(['id', 'name']);
    }

    private function churchForTithes(Request $request, PermissionService $permissions): ?Church
    {
        $church = $permissions->currentChurch($request->user());

        if ($church === null && $request->user()->isGlobalAdministrator()) {
            $church = $permissions->availableChurches($request->user())->first();
            if ($church !== null) {
                $request->session()->put('active_church_id', $church->id);
            }
        }

        return $church;
    }

    private function referenceMonth(Request $request): Carbon
    {
        $month = $request->integer('reference_month');
        $year = $request->integer('reference_year');

        return $month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100
            ? Carbon::create($year, $month, 1)->startOfMonth()
            : today()->startOfMonth();
    }
}
