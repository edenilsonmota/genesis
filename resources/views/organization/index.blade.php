@extends('layouts.app')

@section('title', 'Área e Igrejas')
@section('header', 'Área e Igrejas')

@section('content')
    <div class="mx-auto grid max-w-7xl gap-7">
        <header class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="ui-page-kicker">Configuração organizacional</p>
                <h1 class="mt-1 ui-page-title">Área e Igrejas</h1>
                <p class="mt-2 max-w-2xl ui-page-copy">Gerencie a única área desta instalação e as igrejas vinculadas a ela.</p>
            </div>
        </header>

        @if ($errors->any())
            <section class="ui-alert-error" role="alert" aria-labelledby="validation-errors-title">
                <p class="font-semibold" id="validation-errors-title">Revise os dados informados.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="ui-card overflow-hidden" aria-labelledby="area-title">
            @if ($area)
                <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-start">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="text-xs font-semibold tracking-widest text-text-disabled uppercase">Área da instalação</p>
                            <x-status-badge :status="$area->status" />
                        </div>
                        <h2 class="mt-3 text-2xl font-semibold tracking-tight text-text-primary" id="area-title">{{ $area->name }}</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-text-secondary">{{ $area->description ?: 'Nenhuma descrição informada.' }}</p>

                        <dl class="mt-6 flex flex-wrap gap-8">
                            <div>
                                <dt class="text-xs text-text-secondary">Igrejas cadastradas</dt>
                                <dd class="mt-1 text-2xl font-semibold text-text-primary">{{ $area->churches_count }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-secondary">Igrejas ativas</dt>
                                <dd class="mt-1 text-2xl font-semibold text-text-primary">{{ $area->active_churches_count }}</dd>
                            </div>
                        </dl>
                    </div>

                    <a class="ui-button-outline" href="{{ route('organization.index', ['panel' => 'edit-area']) }}">Editar área</a>
                </div>
            @else
                <div class="grid place-items-center px-6 py-14 text-center sm:px-8">
                    <div class="grid size-14 place-items-center rounded-2xl bg-brand-primary/10 text-xl font-semibold text-brand-primary" aria-hidden="true">A</div>
                    <h2 class="mt-5 text-xl font-semibold text-text-primary" id="area-title">Nenhuma área cadastrada</h2>
                    <p class="mt-2 max-w-md text-sm leading-6 text-text-secondary">Cadastre a configuração organizacional principal antes de adicionar igrejas.</p>
                    <a class="ui-button-primary mt-6 px-5" href="{{ route('organization.index', ['panel' => 'create-area']) }}">Cadastrar área</a>
                </div>
            @endif
        </section>

        <section class="ui-card overflow-hidden" aria-labelledby="churches-title">
            <div class="flex flex-col gap-4 border-b border-border-default p-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <div>
                    <h2 class="text-xl font-semibold text-text-primary" id="churches-title">Igrejas</h2>
                    <p class="mt-1 text-sm text-text-secondary">Todas as igrejas permanecem visíveis, inclusive as inativas.</p>
                </div>

                @if ($area)
                    <a class="ui-button-primary" href="{{ route('organization.index', ['panel' => 'create-church']) }}">Adicionar igreja</a>
                @else
                    <div class="text-right">
                        <button class="ui-button-primary" type="button" disabled>Adicionar igreja</button>
                        <p class="mt-1.5 text-xs text-text-secondary">Cadastre a área primeiro.</p>
                    </div>
                @endif
            </div>

            <form class="grid gap-4 border-b border-border-default bg-surface-muted/60 p-6 sm:grid-cols-2 lg:grid-cols-[minmax(14rem,1fr)_10rem_11rem_13rem_auto] lg:px-8" method="GET" action="{{ route('organization.index') }}" data-dependent-cities data-cities-base-url="{{ url('/organization/states') }}">
                <div>
                    <label class="ui-label text-xs" for="filter-search">Pesquisar</label>
                    <input class="ui-input" id="filter-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nome da igreja">
                </div>
                <div>
                    <label class="ui-label text-xs" for="filter-status">Status</label>
                    <select class="ui-select" id="filter-status" name="status">
                        <option value="">Todos</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativas</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativas</option>
                    </select>
                </div>
                <div>
                    <label class="ui-label text-xs" for="filter-state">Estado</label>
                    <select class="ui-select" id="filter-state" name="state_id" data-state-select>
                        <option value="">Todos</option>
                        @foreach ($states as $state)
                            <option value="{{ $state->id }}" @selected((string) ($filters['state_id'] ?? '') === (string) $state->id)>{{ $state->abbreviation }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="ui-label text-xs" for="filter-city">Cidade</label>
                    <select class="ui-select disabled:bg-surface-muted" id="filter-city" name="city_id" data-city-select @disabled(empty($filters['state_id']))>
                        <option value="">Todas</option>
                        @foreach ($filterCities as $city)
                            <option value="{{ $city->id }}" @selected((string) ($filters['city_id'] ?? '') === (string) $city->id)>{{ $city->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button class="ui-button-primary flex-1" type="submit">Filtrar</button>
                    @if (array_filter([$filters['search'] ?? null, $filters['status'] ?? null, $filters['state_id'] ?? null, $filters['city_id'] ?? null]))
                        <a class="ui-button-outline px-3" href="{{ route('organization.index') }}" aria-label="Limpar filtros">Limpar</a>
                    @endif
                </div>
            </form>

            @if ($churches->isEmpty())
                <div class="px-6 py-14 text-center">
                    <h3 class="font-semibold text-text-primary">{{ $churches->total() === 0 && ! array_filter($filters) ? 'Nenhuma igreja cadastrada' : 'Nenhuma igreja encontrada' }}</h3>
                    <p class="mt-2 text-sm text-text-secondary">{{ $area ? 'Adicione uma igreja ou ajuste os filtros utilizados.' : 'As igrejas aparecerão aqui depois do cadastro da área.' }}</p>
                </div>
            @else
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-border-default bg-surface-muted text-xs font-semibold tracking-wide text-text-secondary uppercase">
                            <tr>
                                <th class="px-8 py-3" scope="col">Igreja</th>
                                <th class="px-5 py-3" scope="col">Localização</th>
                                <th class="px-5 py-3" scope="col">Status</th>
                                <th class="px-8 py-3 text-right" scope="col">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-default/70">
                            @foreach ($churches as $church)
                                <tr>
                                    <td class="px-8 py-4">
                                        <p class="font-semibold text-text-primary">{{ $church->name }}</p>
                                        <p class="mt-1 text-xs text-text-secondary">{{ $church->postal_code ? 'CEP '.substr($church->postal_code, 0, 5).'-'.substr($church->postal_code, 5) : 'Endereço não informado' }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-text-secondary">{{ $church->city ? $church->city->name.' · '.$church->city->state->abbreviation : 'Localização não informada' }}</td>
                                    <td class="px-5 py-4"><x-status-badge :status="$church->status" /></td>
                                    <td class="px-8 py-4">
                                        <div class="flex justify-end gap-2">
                                            <a class="ui-button-outline rounded-lg px-2.5 py-2 text-xs" href="{{ route('organization.index', ['panel' => 'view-church', 'church' => $church]) }}">Visualizar</a>
                                            <a class="ui-button-secondary rounded-lg px-2.5 py-2 text-xs" href="{{ route('organization.index', ['panel' => 'edit-church', 'church' => $church]) }}">Editar</a>
                                            @if ($church->status->value === 'active')
                                                <form method="POST" action="{{ route('organization.churches.inactivate', $church) }}" data-confirm="Inativar esta igreja? Ela continuará visível no histórico.">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="ui-button-danger rounded-lg px-2.5 py-2 text-xs" type="submit">Inativar</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="grid divide-y divide-border-default/70 md:hidden">
                    @foreach ($churches as $church)
                        <article class="grid gap-4 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-text-primary">{{ $church->name }}</h3>
                                    <p class="mt-1 text-sm text-text-secondary">{{ $church->city ? $church->city->name.' · '.$church->city->state->abbreviation : 'Localização não informada' }}</p>
                                </div>
                                <x-status-badge :status="$church->status" />
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a class="ui-button-outline rounded-lg px-3 py-2 text-xs" href="{{ route('organization.index', ['panel' => 'view-church', 'church' => $church]) }}">Visualizar</a>
                                <a class="ui-button-secondary rounded-lg px-3 py-2 text-xs" href="{{ route('organization.index', ['panel' => 'edit-church', 'church' => $church]) }}">Editar</a>
                                @if ($church->status->value === 'active')
                                    <form method="POST" action="{{ route('organization.churches.inactivate', $church) }}" data-confirm="Inativar esta igreja? Ela continuará visível no histórico.">
                                        @csrf
                                        @method('PATCH')
                                        <button class="ui-button-danger rounded-lg px-3 py-2 text-xs" type="submit">Inativar</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="border-t border-border-default px-6 py-4 sm:px-8">
                    {{ $churches->links() }}
                </div>
            @endif
        </section>
    </div>

    @if (($filters['panel'] ?? null) === 'create-area' && ! $area)
        <div class="ui-modal-backdrop grid sm:place-items-center" role="dialog" aria-modal="true" aria-labelledby="area-form-title">
            <section class="my-auto w-full max-w-xl rounded-3xl bg-surface-card p-6 shadow-2xl shadow-brand-primary/15 sm:p-8">
                <h2 class="text-2xl font-semibold text-text-primary" id="area-form-title">Cadastrar área</h2>
                <p class="mt-2 text-sm text-text-secondary">Esta será a única área da instalação.</p>
                <form class="mt-7 grid gap-5" method="POST" action="{{ route('organization.area.store') }}">
                    @csrf
                    @include('organization._area-fields', ['area' => null])
                    <div class="flex flex-col-reverse gap-3 border-t border-border-default pt-5 sm:flex-row sm:justify-end">
                        <a class="ui-button-outline" href="{{ route('organization.index') }}">Cancelar</a>
                        <button class="ui-button-primary px-5" type="submit">Cadastrar área</button>
                    </div>
                </form>
            </section>
        </div>
    @endif

    @if (($filters['panel'] ?? null) === 'edit-area' && $area)
        <div class="ui-modal-backdrop grid sm:place-items-center" role="dialog" aria-modal="true" aria-labelledby="area-form-title">
            <section class="my-auto w-full max-w-xl rounded-3xl bg-surface-card p-6 shadow-2xl shadow-brand-primary/15 sm:p-8">
                <h2 class="text-2xl font-semibold text-text-primary" id="area-form-title">Editar área</h2>
                <form class="mt-7 grid gap-5" method="POST" action="{{ route('organization.area.update', $area) }}">
                    @csrf
                    @method('PUT')
                    @include('organization._area-fields', ['area' => $area])
                    <div class="flex flex-col-reverse gap-3 border-t border-border-default pt-5 sm:flex-row sm:justify-end">
                        <a class="ui-button-outline" href="{{ route('organization.index') }}">Cancelar</a>
                        <button class="ui-button-primary px-5" type="submit">Salvar alterações</button>
                    </div>
                </form>
            </section>
        </div>
    @endif

    @if (($filters['panel'] ?? null) === 'create-church' && $area)
        <div class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="church-form-title">
            <section class="mx-auto my-6 w-full max-w-3xl rounded-3xl bg-surface-card p-6 shadow-2xl shadow-brand-primary/15 sm:p-8">
                <h2 class="text-2xl font-semibold text-text-primary" id="church-form-title">Adicionar igreja</h2>
                <p class="mt-2 text-sm text-text-secondary">A igreja será vinculada automaticamente à área {{ $area->name }}.</p>
                <div class="mt-7">
                    @include('organization._church-form', ['church' => null, 'action' => route('organization.churches.store'), 'method' => 'POST', 'submitLabel' => 'Cadastrar igreja'])
                </div>
            </section>
        </div>
    @endif

    @if (($filters['panel'] ?? null) === 'edit-church' && $selectedChurch)
        <div class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="church-form-title">
            <section class="mx-auto my-6 w-full max-w-3xl rounded-3xl bg-surface-card p-6 shadow-2xl shadow-brand-primary/15 sm:p-8">
                <h2 class="text-2xl font-semibold text-text-primary" id="church-form-title">Editar igreja</h2>
                <div class="mt-7">
                    @include('organization._church-form', ['church' => $selectedChurch, 'action' => route('organization.churches.update', $selectedChurch), 'method' => 'PUT', 'submitLabel' => 'Salvar alterações'])
                </div>
            </section>
        </div>
    @endif

    @if (($filters['panel'] ?? null) === 'view-church' && $selectedChurch)
        <div class="ui-modal-backdrop grid sm:place-items-center" role="dialog" aria-modal="true" aria-labelledby="church-detail-title">
            <section class="my-auto w-full max-w-2xl rounded-3xl bg-surface-card p-6 shadow-2xl shadow-brand-primary/15 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold tracking-widest text-text-disabled uppercase">Igreja</p>
                        <h2 class="mt-2 text-2xl font-semibold text-text-primary" id="church-detail-title">{{ $selectedChurch->name }}</h2>
                    </div>
                    <x-status-badge :status="$selectedChurch->status" />
                </div>
                <dl class="mt-7 grid gap-5 rounded-2xl bg-surface-muted p-5 sm:grid-cols-2">
                    <div><dt class="text-xs text-text-secondary">CEP</dt><dd class="mt-1 font-medium text-text-primary">{{ substr($selectedChurch->postal_code, 0, 5).'-'.substr($selectedChurch->postal_code, 5) }}</dd></div>
                    <div><dt class="text-xs text-text-secondary">Cidade</dt><dd class="mt-1 font-medium text-text-primary">{{ $selectedChurch->city->name }} · {{ $selectedChurch->city->state->abbreviation }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-text-secondary">Endereço</dt><dd class="mt-1 font-medium text-text-primary">{{ $selectedChurch->street }}, {{ $selectedChurch->number }} · {{ $selectedChurch->neighborhood }}{{ $selectedChurch->complement ? ' · '.$selectedChurch->complement : '' }}</dd></div>
                </dl>
                <div class="mt-6 flex justify-end gap-3">
                    <a class="ui-button-outline" href="{{ route('organization.index') }}">Fechar</a>
                    <a class="ui-button-primary" href="{{ route('organization.index', ['panel' => 'edit-church', 'church' => $selectedChurch]) }}">Editar</a>
                </div>
            </section>
        </div>
    @endif
@endsection
