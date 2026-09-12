@extends('layouts.app')

@section('title', 'Membros')
@section('header', 'Membros')

@section('content')
    <div class="mx-auto grid max-w-7xl gap-7">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-genesis-600">Pessoas e vínculos</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight text-ink-950">Membros</h1>
                <p class="mt-2 text-sm text-slate-500">Consulte membros e as igrejas às quais estão vinculados.</p>
            </div>
            @can('create', App\Models\Member::class)
                @if ($hasActiveChurches)
                    <a class="rounded-xl bg-genesis-600 px-5 py-2.5 text-center text-sm font-semibold text-white hover:bg-genesis-700" href="{{ route('members.create') }}">Novo membro</a>
                @else
                    <div class="text-right">
                        <button class="cursor-not-allowed rounded-xl bg-stone-200 px-5 py-2.5 text-sm font-semibold text-stone-500" disabled>Novo membro</button>
                        <p class="mt-1 text-xs text-slate-500">Cadastre uma igreja ativa primeiro.</p>
                    </div>
                @endif
            @endcan
        </header>

        @include('members._validation-errors')

        <section class="overflow-hidden rounded-3xl border border-stone-200 bg-white shadow-sm">
            <form class="grid gap-4 border-b border-stone-200 bg-stone-50/60 p-6 sm:grid-cols-2 xl:grid-cols-[minmax(15rem,1fr)_14rem_10rem_10rem_auto]" method="GET" action="{{ route('members.index') }}">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-slate-600" for="search">Pesquisar</label>
                    <input class="block w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-sm" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nome, CPF, telefone ou e-mail">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-slate-600" for="church_id">Igreja ativa</label>
                    <select class="block w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-sm" id="church_id" name="church_id">
                        <option value="">Todas</option>
                        @foreach ($churches as $church)
                            <option value="{{ $church->id }}" @selected(($filters['church_id'] ?? '') === $church->id)>{{ $church->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-slate-600" for="status">Status</label>
                    <select class="block w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-sm" id="status" name="status">
                        <option value="">Todos</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status === App\Status::Active ? 'Ativos' : 'Inativos' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-slate-600" for="sex">Sexo</label>
                    <select class="block w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-sm" id="sex" name="sex">
                        <option value="">Todos</option>
                        @foreach ($sexes as $sex)
                            <option value="{{ $sex->value }}" @selected(($filters['sex'] ?? '') === $sex->value)>{{ $sex->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button class="flex-1 rounded-xl bg-ink-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800" type="submit">Filtrar</button>
                    @if (array_filter($filters))
                        <a class="rounded-xl border border-stone-300 px-3 py-2.5 text-sm font-semibold text-slate-600" href="{{ route('members.index') }}">Limpar</a>
                    @endif
                </div>
            </form>

            @if ($members->isEmpty())
                <div class="px-6 py-16 text-center">
                    <h2 class="font-semibold text-ink-950">Nenhum membro encontrado</h2>
                    <p class="mt-2 text-sm text-slate-500">Cadastre um membro ou ajuste os filtros utilizados.</p>
                </div>
            @else
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-stone-200 bg-stone-50 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            <tr><th class="px-7 py-3">Membro</th><th class="px-5 py-3">Contato</th><th class="px-5 py-3">Igreja principal</th><th class="px-5 py-3">Acesso</th><th class="px-5 py-3">Status</th><th class="px-7 py-3 text-right">Ações</th></tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($members as $member)
                                <tr>
                                    <td class="px-7 py-4"><p class="font-semibold text-ink-950">{{ $member->name }}</p><p class="mt-1 text-xs text-slate-500">{{ substr($member->cpf, 0, 3).'.'.substr($member->cpf, 3, 3).'.'.substr($member->cpf, 6, 3).'-'.substr($member->cpf, 9) }}</p></td>
                                    <td class="px-5 py-4 text-slate-600">{{ $member->phone ?: ($member->email ?: 'Não informado') }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $member->primaryMembership?->church?->name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $member->user ? 'Possui acesso' : 'Sem acesso' }}</td>
                                    <td class="px-5 py-4"><x-status-badge :status="$member->status" /></td>
                                    <td class="px-7 py-4 text-right"><a class="rounded-lg px-3 py-2 text-xs font-semibold text-genesis-700 hover:bg-genesis-50" href="{{ route('members.show', $member) }}">Visualizar</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="grid divide-y divide-stone-100 md:hidden">
                    @foreach ($members as $member)
                        <article class="grid gap-3 p-5">
                            <div class="flex items-start justify-between gap-3"><div><h2 class="font-semibold text-ink-950">{{ $member->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $member->primaryMembership?->church?->name ?? 'Sem igreja principal' }}</p></div><x-status-badge :status="$member->status" /></div>
                            <p class="text-sm text-slate-600">{{ $member->phone ?: ($member->email ?: 'Contato não informado') }}</p>
                            <a class="w-fit rounded-lg border border-stone-200 px-3 py-2 text-xs font-semibold text-genesis-700" href="{{ route('members.show', $member) }}">Visualizar</a>
                        </article>
                    @endforeach
                </div>
                <div class="border-t border-stone-200 px-6 py-4">{{ $members->links() }}</div>
            @endif
        </section>
    </div>
@endsection
