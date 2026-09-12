@extends('layouts.app')
@section('title', 'Contas e categorias')
@section('header', 'Contas e categorias')
@section('content')
<div class="mx-auto grid max-w-7xl gap-7">
    @include('finance._catalog-header', ['activeTab' => 'accounts'])

    @if ($errors->any())
        <section class="ui-alert-error" role="alert"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>
    @endif

    @if (! $area)
        <section class="rounded-2xl border border-warning/20 bg-warning-soft p-5 text-sm text-warning" role="status">
            <p class="font-semibold">É necessário cadastrar e ativar a área antes de criar contas financeiras.</p>
            @can('create', App\Models\Area::class)
                <a class="mt-3 inline-flex font-semibold text-brand-primary hover:text-brand-primary-hover" href="{{ route('organization.index', ['panel' => 'create-area']) }}">Cadastrar área agora →</a>
            @endcan
        </section>
    @endif

    <section class="ui-card overflow-hidden">
        <div class="flex flex-col gap-4 border-b border-border-default p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-text-primary">Contas financeiras</h2>
                <p class="mt-1 text-sm text-text-secondary">Contas da área e das igrejas, sem saldo manual.</p>
            </div>
            @if ($canWriteFinancialAccounts && $area)
                <a class="ui-button-primary" href="{{ route('finance.accounts.create') }}">Nova conta</a>
            @endif
        </div>

        <form class="grid gap-3 border-b border-border-default bg-surface-muted/60 p-5 sm:grid-cols-2 xl:grid-cols-[minmax(12rem,1fr)_10rem_minmax(12rem,1fr)_12rem_10rem_auto]" method="GET">
            <div>
                <label class="sr-only" for="account-search">Buscar conta</label>
                <input class="ui-input" id="account-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nome ou instituição">
            </div>
            <div>
                <label class="sr-only" for="account-owner-type">Tipo do proprietário</label>
                <select class="ui-select" id="account-owner-type" name="owner_type">
                    <option value="">Todos os escopos</option>
                    <option value="area" @selected(($filters['owner_type'] ?? '') === 'area')>Área</option>
                    <option value="church" @selected(($filters['owner_type'] ?? '') === 'church')>Igreja</option>
                </select>
            </div>
            <div>
                <label class="sr-only" for="account-owner">Proprietário</label>
                <select class="ui-select" id="account-owner" name="owner_id">
                    <option value="">Todos os proprietários</option>
                    @if ($area)
                        <option value="{{ $area->id }}" @selected(($filters['owner_id'] ?? '') === $area->id)>Área · {{ $area->name }}</option>
                    @endif
                    @foreach ($churches as $church)
                        <option value="{{ $church->id }}" @selected(($filters['owner_id'] ?? '') === $church->id)>Igreja · {{ $church->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="sr-only" for="account-type">Tipo da conta</label>
                <select class="ui-select" id="account-type" name="type">
                    <option value="">Todos os tipos</option>
                    @foreach ($accountTypes as $accountType)
                        <option value="{{ $accountType->value }}" @selected(($filters['type'] ?? '') === $accountType->value)>{{ $accountType->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="sr-only" for="account-status">Status</label>
                <select class="ui-select" id="account-status" name="status">
                    <option value="">Todos os status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativas</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativas</option>
                </select>
            </div>
            <button class="ui-button-primary" type="submit">Filtrar</button>
        </form>

        <div class="divide-y divide-border-default">
            @forelse ($accounts as $account)
                <article class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_12rem_11rem_8rem_auto] lg:items-center">
                    <div class="min-w-0">
                        <h3 class="truncate font-semibold text-text-primary">{{ $account->name }}</h3>
                        <p class="mt-1 truncate text-sm text-text-secondary">{{ $account->institution ?: 'Sem instituição informada' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-text-disabled uppercase">Proprietário</p>
                        <p class="mt-1 truncate text-sm text-text-secondary">{{ $account->area?->name ?? $account->church?->name }}</p>
                        <p class="text-xs text-text-disabled">{{ $account->area_id !== null ? 'Área' : 'Igreja' }}</p>
                    </div>
                    <p class="text-sm text-text-secondary">{{ $account->type->label() }}</p>
                    <x-status-badge :status="$account->status" />
                    <div class="flex flex-wrap gap-2">
                        @if ($canWriteFinancialAccounts)
                            <a class="ui-button-outline px-3 py-2 text-xs" href="{{ route('finance.accounts.edit', $account) }}">Editar</a>
                            @if ($account->status === App\Status::Active)
                                <form method="POST" action="{{ route('finance.accounts.inactivate', $account) }}" data-confirm="Inativar esta conta financeira? Ela permanecerá disponível no histórico e não poderá receber novas movimentações.">
                                    @csrf
                                    @method('PATCH')
                                    <button class="ui-button-danger px-3 py-2 text-xs" type="submit">Inativar</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('finance.accounts.activate', $account) }}" data-confirm="Ativar esta conta financeira?">
                                    @csrf
                                    @method('PATCH')
                                    <button class="ui-button-secondary px-3 py-2 text-xs" type="submit">Ativar</button>
                                </form>
                            @endif
                        @endif
                    </div>
                </article>
            @empty
                <div class="grid justify-items-center gap-3 px-6 py-14 text-center">
                    <span class="grid size-12 place-items-center rounded-2xl bg-brand-primary-soft text-brand-primary" aria-hidden="true">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v11H4zM7 11h4v4H7zM14 11h3M14 15h3" /></svg>
                    </span>
                    <p class="max-w-xl text-sm leading-6 text-text-secondary">Cadastre as contas utilizadas pela área e pelas igrejas para preparar o controle das movimentações financeiras.</p>
                </div>
            @endforelse
        </div>
        <div class="p-4">{{ $accounts->links() }}</div>
    </section>
</div>
@endsection
