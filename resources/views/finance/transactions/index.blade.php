@extends('layouts.app')
@section('title', 'Movimentações')
@section('header', 'Movimentações')
@section('content')
<div class="mx-auto grid max-w-7xl gap-7">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="ui-page-kicker">Financeiro</p>
            <h1 class="mt-1 ui-page-title">Movimentações</h1>
            <p class="mt-2 ui-page-copy">Entradas, saídas, transferências e seu ciclo de liquidação em um único livro financeiro.</p>
        </div>
        @if ($canWriteFinancialTransactions)
            <a class="ui-button-primary" href="{{ route('finance.transactions.create') }}">Nova movimentação</a>
        @endif
    </header>

    @if ($errors->any())
        <section class="ui-alert-error" role="alert"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>
    @endif

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumo do período filtrado">
        @foreach ([
            ['label' => 'Entradas liquidadas', 'value' => $summary['inflows'], 'class' => 'text-success'],
            ['label' => 'Saídas liquidadas', 'value' => $summary['outflows'], 'class' => 'text-danger'],
            ['label' => 'Resultado', 'value' => $summary['result'], 'class' => (float) $summary['result'] < 0 ? 'text-danger' : 'text-brand-primary'],
            ['label' => 'Total transferido', 'value' => $summary['transferred'], 'class' => 'text-info'],
        ] as $item)
            <article class="ui-card p-5">
                <p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">{{ $item['label'] }}</p>
                <p class="mt-2 text-xl font-semibold {{ $item['class'] }}">R$ {{ number_format((float) $item['value'], 2, ',', '.') }}</p>
            </article>
        @endforeach
    </section>

    <section class="ui-card overflow-hidden">
        <nav class="flex gap-1 overflow-x-auto border-b border-border-default p-3" aria-label="Tipos de movimentação">
            @foreach ([null => 'Todas', 'income' => 'Entradas', 'expense' => 'Saídas', 'transfer' => 'Transferências'] as $type => $label)
                @php $tabQuery = request()->except('page', 'type'); if ($type) $tabQuery['type'] = $type; @endphp
                <a @class([
                    'shrink-0 rounded-xl px-4 py-2 text-sm font-semibold transition',
                    'bg-brand-primary-soft text-brand-primary' => ($filters['type'] ?? null) === $type,
                    'text-text-secondary hover:bg-surface-muted hover:text-text-primary' => ($filters['type'] ?? null) !== $type,
                ]) href="{{ route('finance.transactions.index', $tabQuery) }}" @if(($filters['type'] ?? null) === $type) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($filters['default_period'] ?? false)
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-info/20 bg-info-soft px-5 py-3 text-sm text-info" role="status">
                <span>Filtro padrão: mês atual ({{ today()->format('m/Y') }}).</span>
                <a class="font-semibold text-brand-primary hover:text-brand-primary-hover" href="{{ route('finance.transactions.index', ['all_periods' => 1]) }}">Ver todo o histórico</a>
            </div>
        @endif

        <details class="border-b border-border-default bg-surface-muted/60" @if(! empty($filters['search']) || ! empty($filters['status']) || ! empty($filters['scope_type']) || ! empty($filters['scope_id']) || ! empty($filters['account_id']) || ! empty($filters['category_id']) || ! empty($filters['department_id']) || ! empty($filters['responsible_member_id'])) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 text-sm font-semibold text-text-primary marker:content-none"><span class="flex items-center gap-2"><svg class="size-4 text-brand-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M7 12h10m-7 6h4" /></svg>Filtros</span><span class="text-xs font-medium text-text-secondary">Expandir ou recolher</span></summary>
        <form class="grid gap-3 border-t border-border-default p-5 md:grid-cols-2 xl:grid-cols-4" method="GET">
            <input type="hidden" name="all_periods" value="1">
            <div class="xl:col-span-2">
                <label class="ui-label" for="transaction-search">Busca</label>
                <input class="ui-input" id="transaction-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Título, descrição, contraparte ou documento">
            </div>
            <div>
                <label class="ui-label" for="date_from">Data inicial</label>
                <input class="ui-input" id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label class="ui-label" for="date_to">Data final</label>
                <input class="ui-input" id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div>
                <label class="ui-label" for="filter-type">Tipo</label>
                <select class="ui-select" id="filter-type" name="type">
                    <option value="">Todos</option>
                    @foreach ($transactionTypes as $type)<option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="ui-label" for="filter-status">Status</label>
                <select class="ui-select" id="filter-status" name="status">
                    <option value="">Todos</option>
                    @foreach ($transactionStatuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="ui-label" for="scope_type">Tipo de escopo</label>
                <select class="ui-select" id="scope_type" name="scope_type">
                    <option value="">Área e igrejas</option>
                    @if ($areas->isNotEmpty())<option value="area" @selected(($filters['scope_type'] ?? '') === 'area')>Área</option>@endif
                    @if ($churches->isNotEmpty())<option value="church" @selected(($filters['scope_type'] ?? '') === 'church')>Igreja</option>@endif
                </select>
            </div>
            <div>
                <label class="ui-label" for="scope_id">Área ou igreja</label>
                <select class="ui-select" id="scope_id" name="scope_id">
                    <option value="">Todos os escopos</option>
                    @foreach ($areas as $area)<option value="{{ $area->id }}" @selected(($filters['scope_id'] ?? '') === $area->id)>Área · {{ $area->name }}</option>@endforeach
                    @foreach ($churches as $church)<option value="{{ $church->id }}" @selected(($filters['scope_id'] ?? '') === $church->id)>Igreja · {{ $church->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="ui-label" for="filter-account">Conta</label>
                <select class="ui-select" id="filter-account" name="account_id"><option value="">Todas</option>@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected(($filters['account_id'] ?? '') === $account->id)>{{ $account->name }}</option>@endforeach</select>
            </div>
            <div>
                <label class="ui-label" for="filter-category">Categoria</label>
                <select class="ui-select" id="filter-category" name="category_id"><option value="">Todas</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') === $category->id)>{{ $category->name }}</option>@endforeach</select>
            </div>
            <div>
                <label class="ui-label" for="filter-department">Departamento</label>
                <select class="ui-select" id="filter-department" name="department_id"><option value="">Todos</option>@foreach ($departments as $department)<option value="{{ $department->id }}" @selected(($filters['department_id'] ?? '') === $department->id)>{{ $department->name }}</option>@endforeach</select>
            </div>
            <div>
                <label class="ui-label" for="filter-responsible">Responsável</label>
                <select class="ui-select" id="filter-responsible" name="responsible_member_id"><option value="">Todos</option>@foreach ($responsibleMembers as $member)<option value="{{ $member->id }}" @selected(($filters['responsible_member_id'] ?? '') === $member->id)>{{ $member->name }}</option>@endforeach</select>
            </div>
            <div class="flex flex-wrap items-end gap-2 xl:col-span-4">
                <button class="ui-button-primary" type="submit">Aplicar filtros</button>
                <a class="ui-button-outline" href="{{ route('finance.transactions.index', ['all_periods' => 1]) }}">Limpar</a>
            </div>
        </form>
        </details>

        <div class="divide-y divide-border-default">
            <div class="hidden bg-surface-muted px-5 py-3 text-xs font-semibold tracking-wide text-text-secondary uppercase xl:grid xl:grid-cols-[6.5rem_minmax(12rem,1.4fr)_minmax(10rem,1fr)_9rem_8rem_auto] xl:items-center xl:gap-4">
                <span>Data</span><span>Lançamento</span><span>Responsável</span><span>Valor</span><span>Status</span><span class="text-right">Ações</span>
            </div>
            @forelse ($transactions as $transaction)
                @php
                    $outflow = $transaction->movements->firstWhere('direction', App\Enums\FinancialMovementDirection::Outflow);
                    $inflow = $transaction->movements->firstWhere('direction', App\Enums\FinancialMovementDirection::Inflow);
                    $accountLabel = $transaction->type === App\Enums\FinancialTransactionType::Transfer
                        ? ($outflow?->account?->name.' → '.$inflow?->account?->name)
                        : ($transaction->movements->first()?->account?->name ?? 'Conta indisponível');
                    $titleLabel = $transaction->title;
                    if ($transaction->origin === App\Enums\FinancialTransactionOrigin::Tithe && $transaction->competence_month) {
                        $titleLabel .= ' · '.$transaction->competence_month->format('m/Y');
                    }
                @endphp
                <article class="grid gap-4 p-5 xl:grid-cols-[6.5rem_minmax(12rem,1.4fr)_minmax(10rem,1fr)_9rem_8rem_auto] xl:items-center">
                    <time class="text-sm font-medium text-text-secondary" datetime="{{ $transaction->occurred_on->toDateString() }}">{{ $transaction->occurred_on->format('d/m/Y') }}</time>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold text-text-primary">{{ $titleLabel }}</h2>
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-success-soft text-success' => $transaction->type === App\Enums\FinancialTransactionType::Income,
                                'bg-danger-soft text-danger' => $transaction->type === App\Enums\FinancialTransactionType::Expense,
                                'bg-info-soft text-info' => $transaction->type === App\Enums\FinancialTransactionType::Transfer,
                                'bg-warning-soft text-warning' => $transaction->type === App\Enums\FinancialTransactionType::Reversal,
                            ])>{{ $transaction->type->label() }}</span>
                            @if ($transaction->reversed_at || $transaction->type === App\Enums\FinancialTransactionType::Reversal)<span class="rounded-full bg-warning-soft px-2.5 py-1 text-xs font-semibold text-warning">Estornado</span>@endif
                        </div>
                        <p class="mt-1 truncate text-sm text-text-secondary">{{ $accountLabel }}</p>
                        <p class="mt-1 text-xs text-text-secondary">{{ $transaction->category?->name ?? 'Sem categoria' }} · {{ $transaction->department?->name ?? 'Sem departamento' }}</p>
                    </div>
                    <div class="text-sm text-text-secondary">
                        <p>{{ $transaction->responsibleMember?->name ?? 'Sem responsável' }}</p>
                        <p class="mt-1 text-xs">Registrado por {{ $transaction->createdByUser->display_name }}</p>
                    </div>
                    <p @class(['font-semibold', 'text-danger' => in_array($transaction->type, [App\Enums\FinancialTransactionType::Expense, App\Enums\FinancialTransactionType::Reversal], true), 'text-success' => $transaction->type === App\Enums\FinancialTransactionType::Income, 'text-info' => $transaction->type === App\Enums\FinancialTransactionType::Transfer])>R$ {{ number_format((float) $transaction->amount, 2, ',', '.') }}</p>
                    <span @class([
                        'w-fit rounded-full px-2.5 py-1 text-xs font-semibold',
                        'bg-surface-muted text-text-secondary' => $transaction->status === App\Enums\FinancialTransactionStatus::Draft,
                        'bg-warning-soft text-warning' => $transaction->status === App\Enums\FinancialTransactionStatus::Pending,
                        'bg-success-soft text-success' => $transaction->status === App\Enums\FinancialTransactionStatus::Settled,
                        'bg-danger-soft text-danger' => $transaction->status === App\Enums\FinancialTransactionStatus::Cancelled,
                    ])>{{ $transaction->status->label() }}</span>
                    <div class="flex flex-wrap gap-2 xl:justify-end">
                        <a class="ui-button-outline px-3 py-2 text-xs" href="{{ route('finance.transactions.show', $transaction) }}">Visualizar</a>
                        @can('update', $transaction)<a class="ui-button-secondary px-3 py-2 text-xs" href="{{ route('finance.transactions.edit', $transaction) }}">Editar</a>@endcan
                        @can('settle', $transaction)<a class="ui-button-secondary px-3 py-2 text-xs" href="{{ route('finance.transactions.show', $transaction) }}#settle-transaction">Liquidar</a>@endcan
                        @can('cancel', $transaction)<a class="ui-button-danger px-3 py-2 text-xs" href="{{ route('finance.transactions.show', $transaction) }}#cancel-transaction">Cancelar</a>@endcan
                        @can('reverse', $transaction)<a class="ui-button-danger px-3 py-2 text-xs" href="{{ route('finance.transactions.show', $transaction) }}#reverse-transaction">Estornar</a>@endcan
                    </div>
                </article>
            @empty
                <div class="grid justify-items-center gap-3 px-6 py-14 text-center">
                    <span class="grid size-12 place-items-center rounded-2xl bg-brand-primary-soft text-brand-primary" aria-hidden="true"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 7h14M15 3l4 4-4 4M19 17H5M9 13l-4 4 4 4" /></svg></span>
                    <p class="max-w-xl text-sm leading-6 text-text-secondary">Nenhuma movimentação foi encontrada no período e nos filtros informados.</p>
                </div>
            @endforelse
        </div>
        <div class="p-4">{{ $transactions->links() }}</div>
    </section>
</div>
@endsection
