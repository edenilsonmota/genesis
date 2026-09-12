@extends('layouts.app')
@section('title', 'Contas e categorias')
@section('header', 'Contas e categorias')
@section('content')
<div class="mx-auto grid max-w-7xl gap-7">
    @include('finance._catalog-header', ['activeTab' => 'categories'])

    @if ($errors->any())
        <section class="ui-alert-error" role="alert"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>
    @endif

    @if (! $area)
        <section class="rounded-2xl border border-warning/20 bg-warning-soft p-5 text-sm text-warning" role="status">
            <p class="font-semibold">É necessário cadastrar e ativar a área antes de criar categorias financeiras.</p>
            @can('create', App\Models\Area::class)
                <a class="mt-3 inline-flex font-semibold text-brand-primary hover:text-brand-primary-hover" href="{{ route('organization.index', ['panel' => 'create-area']) }}">Cadastrar área agora →</a>
            @endcan
        </section>
    @endif

    <section class="ui-card overflow-hidden">
        <div class="flex flex-col gap-4 border-b border-border-default p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-text-primary">Categorias financeiras</h2>
                <p class="mt-1 text-sm text-text-secondary">Catálogo único da área, disponível para todas as igrejas.</p>
            </div>
            @if ($canWriteFinancialCategories && $area)
                <a class="ui-button-primary" href="{{ route('finance.categories.create') }}">Nova categoria</a>
            @endif
        </div>

        <form class="grid gap-3 border-b border-border-default bg-surface-muted/60 p-5 sm:grid-cols-2 lg:grid-cols-[minmax(12rem,1fr)_12rem_12rem_auto]" method="GET">
            <div>
                <label class="sr-only" for="category-search">Buscar categoria</label>
                <input class="ui-input" id="category-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nome ou descrição">
            </div>
            <div>
                <label class="sr-only" for="category-type">Tipo</label>
                <select class="ui-select" id="category-type" name="type">
                    <option value="">Entradas e saídas</option>
                    @foreach ($categoryTypes as $categoryType)
                        <option value="{{ $categoryType->value }}" @selected(($filters['type'] ?? '') === $categoryType->value)>{{ $categoryType->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="sr-only" for="category-status">Status</label>
                <select class="ui-select" id="category-status" name="status">
                    <option value="">Todos os status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativas</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativas</option>
                </select>
            </div>
            <button class="ui-button-primary" type="submit">Filtrar</button>
        </form>

        <div class="divide-y divide-border-default">
            @forelse ($categories as $category)
                <article class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_9rem_8rem_auto] lg:items-center">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-text-primary">{{ $category->name }}</h3>
                            @if ($category->fixed)
                                <span class="rounded-full bg-surface-muted px-2.5 py-1 text-xs font-semibold text-text-secondary">Fixa</span>
                            @endif
                        </div>
                        <p class="mt-1 truncate text-sm text-text-secondary">{{ $category->description ?: 'Sem descrição' }}</p>
                    </div>
                    <span @class([
                        'w-fit rounded-full px-2.5 py-1 text-xs font-semibold',
                        'bg-success-soft text-success' => $category->type === App\Enums\FinancialCategoryType::Income,
                        'bg-danger-soft text-danger' => $category->type === App\Enums\FinancialCategoryType::Expense,
                    ])>{{ $category->type->label() }}</span>
                    <x-status-badge :status="$category->status" />
                    <div class="flex flex-wrap gap-2">
                        @if ($canWriteFinancialCategories && ! $category->fixed)
                            <a class="ui-button-outline px-3 py-2 text-xs" href="{{ route('finance.categories.edit', $category) }}">Editar</a>
                            @if ($category->status === App\Status::Active)
                                <form method="POST" action="{{ route('finance.categories.inactivate', $category) }}" data-confirm="Inativar esta categoria financeira? Ela permanecerá disponível no histórico e não poderá ser usada em novos lançamentos.">
                                    @csrf
                                    @method('PATCH')
                                    <button class="ui-button-danger px-3 py-2 text-xs" type="submit">Inativar</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('finance.categories.activate', $category) }}" data-confirm="Ativar esta categoria financeira?">
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
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 5h14v14H5zM8 9h8M8 13h5M8 17h3" /></svg>
                    </span>
                    <p class="max-w-xl text-sm leading-6 text-text-secondary">Nenhuma categoria financeira encontrada para a área.</p>
                </div>
            @endforelse
        </div>
        <div class="p-4">{{ $categories->links() }}</div>
    </section>
</div>
@endsection
