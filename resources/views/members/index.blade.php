@extends('layouts.app')

@section('title', 'Membros')
@section('header', 'Membros')

@section('content')
    <div class="mx-auto grid max-w-7xl gap-7">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="ui-page-kicker">Pessoas e vínculos</p>
                <h1 class="mt-1 ui-page-title">Membros</h1>
                <p class="mt-2 ui-page-copy">Consulte membros e as igrejas às quais estão vinculados.</p>
            </div>
            @can('create', App\Models\Member::class)
                @if ($hasActiveChurches)
                    <a class="ui-button-primary px-5" href="{{ route('members.create') }}">Novo membro</a>
                @else
                    <div class="text-right">
                        <button class="ui-button-primary px-5" disabled>Novo membro</button>
                        <p class="mt-1 text-xs text-text-secondary">Cadastre uma igreja ativa primeiro.</p>
                    </div>
                @endif
            @endcan
        </header>

        @include('members._validation-errors')

        <section class="ui-card overflow-hidden">
            <form class="grid gap-4 border-b border-border-default bg-surface-muted/60 p-6 sm:grid-cols-2 xl:grid-cols-[minmax(15rem,1fr)_14rem_10rem_10rem_auto]" method="GET" action="{{ route('members.index') }}">
                <div>
                    <label class="ui-label text-xs" for="search">Pesquisar</label>
                    <input class="ui-input" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nome, CPF, telefone ou e-mail">
                </div>
                <div>
                    <label class="ui-label text-xs" for="church_id">Igreja ativa</label>
                    <select class="ui-select" id="church_id" name="church_id">
                        <option value="">Todas</option>
                        @foreach ($churches as $church)
                            <option value="{{ $church->id }}" @selected(($filters['church_id'] ?? '') === $church->id)>{{ $church->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="ui-label text-xs" for="status">Status</label>
                    <select class="ui-select" id="status" name="status">
                        <option value="">Todos</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status === App\Status::Active ? 'Ativos' : 'Inativos' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="ui-label text-xs" for="sex">Sexo</label>
                    <select class="ui-select" id="sex" name="sex">
                        <option value="">Todos</option>
                        @foreach ($sexes as $sex)
                            <option value="{{ $sex->value }}" @selected(($filters['sex'] ?? '') === $sex->value)>{{ $sex->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button class="ui-button-primary flex-1" type="submit">Filtrar</button>
                    @if (array_filter($filters))
                        <a class="ui-button-outline px-3" href="{{ route('members.index') }}">Limpar</a>
                    @endif
                </div>
            </form>

            @if ($members->isEmpty())
                <div class="px-6 py-16 text-center">
                    <h2 class="font-semibold text-text-primary">Nenhum membro encontrado</h2>
                    <p class="mt-2 text-sm text-text-secondary">Cadastre um membro ou ajuste os filtros utilizados.</p>
                </div>
            @else
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-border-default bg-surface-muted text-xs font-semibold tracking-wide text-text-secondary uppercase">
                            <tr><th class="px-7 py-3" scope="col">Membro</th><th class="px-5 py-3" scope="col">Contato</th><th class="px-5 py-3" scope="col">Igreja principal</th><th class="px-5 py-3" scope="col">Acesso</th><th class="px-5 py-3" scope="col">Status</th><th class="px-7 py-3 text-right" scope="col">Ações</th></tr>
                        </thead>
                        <tbody class="divide-y divide-border-default/70">
                            @foreach ($members as $member)
                                <tr>
                                    <td class="px-7 py-4"><p class="font-semibold text-text-primary">{{ $member->name }}</p><p class="mt-1 text-xs text-text-secondary">{{ substr($member->cpf, 0, 3).'.'.substr($member->cpf, 3, 3).'.'.substr($member->cpf, 6, 3).'-'.substr($member->cpf, 9) }}</p></td>
                                    <td class="px-5 py-4 text-text-secondary">{{ $member->phone ?: ($member->email ?: 'Não informado') }}</td>
                                    <td class="px-5 py-4 text-text-secondary">{{ $member->primaryMembership?->church?->name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-text-secondary">{{ $member->user ? 'Possui acesso' : 'Sem acesso' }}</td>
                                    <td class="px-5 py-4"><x-status-badge :status="$member->status" /></td>
                                    <td class="px-7 py-4 text-right"><a class="ui-button-secondary rounded-lg px-3 py-2 text-xs" href="{{ route('members.show', $member) }}">Visualizar</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="grid divide-y divide-border-default/70 md:hidden">
                    @foreach ($members as $member)
                        <article class="grid gap-3 p-5">
                            <div class="flex items-start justify-between gap-3"><div><h2 class="font-semibold text-text-primary">{{ $member->name }}</h2><p class="mt-1 text-sm text-text-secondary">{{ $member->primaryMembership?->church?->name ?? 'Sem igreja principal' }}</p></div><x-status-badge :status="$member->status" /></div>
                            <p class="text-sm text-text-secondary">{{ $member->phone ?: ($member->email ?: 'Contato não informado') }}</p>
                            <a class="ui-button-outline w-fit rounded-lg px-3 py-2 text-xs text-brand-primary" href="{{ route('members.show', $member) }}">Visualizar</a>
                        </article>
                    @endforeach
                </div>
                <div class="border-t border-border-default px-6 py-4">{{ $members->links() }}</div>
            @endif
        </section>
    </div>
@endsection
