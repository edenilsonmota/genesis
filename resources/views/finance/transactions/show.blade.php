@extends('layouts.app')
@section('title', 'Detalhes da movimentação')
@section('header', 'Movimentações')
@section('content')
<div class="mx-auto grid max-w-5xl gap-7">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="ui-page-kicker">Financeiro · {{ $financialTransaction->type->label() }}</p>
            <h1 class="mt-1 ui-page-title">{{ $financialTransaction->title }}</h1>
            <p class="mt-2 ui-page-copy">Registrado em {{ $financialTransaction->created_at->format('d/m/Y H:i') }} por {{ $financialTransaction->createdByUser->display_name }}.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="ui-button-outline" href="{{ route('finance.transactions.index') }}">Voltar</a>
            @can('update', $financialTransaction)<a class="ui-button-primary" href="{{ route('finance.transactions.edit', $financialTransaction) }}">Editar</a>@endcan
        </div>
    </header>

    <section class="ui-card grid gap-6 p-6 sm:grid-cols-2 sm:p-8" aria-label="Dados da movimentação">
        <div>
            <p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Tipo e status</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <span class="rounded-full bg-brand-primary-soft px-3 py-1 text-sm font-semibold text-brand-primary">{{ $financialTransaction->type->label() }}</span>
                <span @class([
                    'rounded-full px-3 py-1 text-sm font-semibold',
                    'bg-surface-muted text-text-secondary' => $financialTransaction->status === App\Enums\FinancialTransactionStatus::Draft,
                    'bg-warning-soft text-warning' => $financialTransaction->status === App\Enums\FinancialTransactionStatus::Pending,
                    'bg-success-soft text-success' => $financialTransaction->status === App\Enums\FinancialTransactionStatus::Settled,
                    'bg-danger-soft text-danger' => $financialTransaction->status === App\Enums\FinancialTransactionStatus::Cancelled,
                ])>{{ $financialTransaction->status->label() }}</span>
                @if ($financialTransaction->reversed_at)<span class="rounded-full bg-warning-soft px-3 py-1 text-sm font-semibold text-warning">Estornado</span>@endif
            </div>
        </div>
        <div>
            <p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Valor</p>
            <p class="mt-2 text-2xl font-semibold text-text-primary">R$ {{ number_format((float) $financialTransaction->amount, 2, ',', '.') }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Data e competência</p>
            <p class="mt-2 text-sm text-text-primary">{{ $financialTransaction->occurred_on->format('d/m/Y') }}</p>
            <p class="mt-1 text-sm text-text-secondary">Competência: {{ $financialTransaction->competence_month?->format('m/Y') ?? 'Não informada' }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Classificação</p>
            <p class="mt-2 text-sm text-text-primary">{{ $financialTransaction->category?->name ?? 'Sem categoria' }}</p>
            <p class="mt-1 text-sm text-text-secondary">{{ $financialTransaction->department?->name ?? 'Sem departamento' }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Responsável e contraparte</p>
            <p class="mt-2 text-sm text-text-primary">{{ $financialTransaction->responsibleMember?->name ?? 'Sem responsável' }}</p>
            <p class="mt-1 text-sm text-text-secondary">{{ $financialTransaction->counterparty_name ?? 'Sem contraparte' }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Pagamento e documento</p>
            <p class="mt-2 text-sm text-text-primary">{{ $financialTransaction->payment_method?->label() ?? 'Não informado' }}</p>
            <p class="mt-1 text-sm text-text-secondary">Documento: {{ $financialTransaction->document_number ?? 'Não informado' }}</p>
        </div>
        <div class="sm:col-span-2">
            <p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Descrição</p>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-text-primary">{{ $financialTransaction->description ?? 'Sem descrição.' }}</p>
        </div>
    </section>

    <section class="ui-card overflow-hidden">
        <div class="border-b border-border-default p-5">
            <h2 class="text-lg font-semibold text-text-primary">Movimentos nas contas</h2>
            <p class="mt-1 text-sm text-text-secondary">O saldo é calculado exclusivamente pelos movimentos liquidados.</p>
        </div>
        <div class="divide-y divide-border-default">
            @foreach ($financialTransaction->movements as $movement)
                <article class="grid gap-3 p-5 sm:grid-cols-[1fr_auto_auto] sm:items-center">
                    <div>
                        <p class="font-semibold text-text-primary">{{ $movement->account->name }}</p>
                        <p class="mt-1 text-sm text-text-secondary">{{ $movement->account->area?->name ? 'Área · '.$movement->account->area->name : 'Igreja · '.$movement->account->church?->name }}</p>
                    </div>
                    <span @class(['w-fit rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-success-soft text-success' => $movement->direction === App\Enums\FinancialMovementDirection::Inflow, 'bg-danger-soft text-danger' => $movement->direction === App\Enums\FinancialMovementDirection::Outflow])>{{ $movement->direction->label() }}</span>
                    <div class="sm:text-right">
                        <p class="font-semibold text-text-primary">R$ {{ number_format((float) $movement->amount, 2, ',', '.') }}</p>
                        <p class="mt-1 text-xs text-text-secondary">{{ $movement->settled_on ? 'Liquidado em '.$movement->settled_on->format('d/m/Y') : 'Não liquidado' }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    @if ($financialTransaction->status === App\Enums\FinancialTransactionStatus::Cancelled)
        <section class="rounded-2xl border border-danger/20 bg-danger-soft p-5 text-sm text-danger">
            <h2 class="font-semibold">Cancelado em {{ $financialTransaction->cancelled_at->format('d/m/Y H:i') }}</h2>
            <p class="mt-2">{{ $financialTransaction->cancellation_reason }}</p>
        </section>
    @endif

    @if ($financialTransaction->reversed_at)
        <section class="rounded-2xl border border-warning/20 bg-warning-soft p-5 text-sm text-warning">
            <h2 class="font-semibold">Estornado em {{ $financialTransaction->reversed_at->format('d/m/Y H:i') }}</h2>
            <p class="mt-2">{{ $financialTransaction->reversal_reason }}</p>
            @if ($financialTransaction->reversalTransaction)<a class="mt-3 inline-flex font-semibold text-brand-primary" href="{{ route('finance.transactions.show', $financialTransaction->reversalTransaction) }}">Ver transação de estorno →</a>@endif
        </section>
    @endif

    @if ($financialTransaction->reversalOfTransaction)
        <section class="rounded-2xl border border-warning/20 bg-warning-soft p-5 text-sm text-warning">
            Esta transação neutraliza integralmente <a class="font-semibold text-brand-primary" href="{{ route('finance.transactions.show', $financialTransaction->reversalOfTransaction) }}">o lançamento original</a>.
        </section>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        @can('settle', $financialTransaction)
            <form id="settle-transaction" class="ui-card grid gap-4 p-6" method="POST" action="{{ route('finance.transactions.settle', $financialTransaction) }}" data-confirm="Liquidar esta movimentação? Ela passará a afetar o saldo e os dados financeiros não poderão mais ser editados.">
                @csrf
                @method('PATCH')
                <div><h2 class="font-semibold text-text-primary">Liquidar movimentação</h2><p class="mt-1 text-sm text-text-secondary">Confirme a data em que o dinheiro entrou, saiu ou foi transferido.</p></div>
                <div><label class="ui-label" for="settled_on">Data da liquidação</label><input class="ui-input" id="settled_on" name="settled_on" type="date" value="{{ today()->toDateString() }}" required></div>
                <button class="ui-button-primary justify-self-start" type="submit">Liquidar agora</button>
            </form>
        @endcan

        @can('cancel', $financialTransaction)
            <form id="cancel-transaction" class="ui-card grid gap-4 p-6" method="POST" action="{{ route('finance.transactions.cancel', $financialTransaction) }}" data-confirm="Cancelar esta movimentação? Ela não afetará saldo, não poderá ser reativada e permanecerá no histórico.">
                @csrf
                @method('PATCH')
                <div><h2 class="font-semibold text-text-primary">Cancelar movimentação</h2><p class="mt-1 text-sm text-text-secondary">Disponível somente antes da liquidação. Informe o motivo para preservar a rastreabilidade.</p></div>
                <div><label class="ui-label" for="cancellation_reason">Motivo</label><textarea class="ui-input min-h-24" id="cancellation_reason" name="reason" minlength="5" maxlength="2000" required></textarea></div>
                <button class="ui-button-danger justify-self-start" type="submit">Cancelar movimentação</button>
            </form>
        @endcan

        @can('reverse', $financialTransaction)
            <form id="reverse-transaction" class="ui-card grid gap-4 p-6 lg:col-span-2" method="POST" action="{{ route('finance.transactions.reverse', $financialTransaction) }}" data-confirm="Estornar este lançamento liquidado? O original será preservado e uma nova transação com movimentos opostos neutralizará o saldo.">
                @csrf
                <div><h2 class="font-semibold text-text-primary">Estornar lançamento</h2><p class="mt-1 text-sm text-text-secondary">O estorno não apaga os movimentos originais e não pode ser repetido.</p></div>
                <div><label class="ui-label" for="reversal_reason">Motivo</label><textarea class="ui-input min-h-24" id="reversal_reason" name="reason" minlength="5" maxlength="2000" required></textarea></div>
                <button class="ui-button-danger justify-self-start" type="submit">Criar estorno</button>
            </form>
        @endcan
    </div>
</div>
@endsection
