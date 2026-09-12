@extends('layouts.app')

@section('title', $member->name)
@section('header', 'Detalhes do membro')

@section('content')
    @php
        $cpf = substr($member->cpf, 0, 3).'.'.substr($member->cpf, 3, 3).'.'.substr($member->cpf, 6, 3).'-'.substr($member->cpf, 9);
        $activeMemberships = $member->memberships->filter(fn ($membership) => $membership->status === App\Status::Active && $membership->ended_at === null);
    @endphp
    <div class="mx-auto grid max-w-6xl gap-7">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><a class="text-sm font-semibold text-genesis-700" href="{{ route('members.index') }}">← Voltar para membros</a><div class="mt-3 flex flex-wrap items-center gap-3"><h1 class="text-3xl font-semibold tracking-tight text-ink-950">{{ $member->name }}</h1><x-status-badge :status="$member->status" /></div><p class="mt-2 text-sm text-slate-500">CPF {{ $cpf }}</p></div>
            @can('update', $member)
                <div class="flex flex-wrap gap-2">
                    <a class="rounded-xl border border-stone-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-white" href="{{ route('members.edit', $member) }}">Editar dados</a>
                    @if ($member->status === App\Status::Active)
                        <form method="POST" action="{{ route('members.inactivate', $member) }}" data-confirm="Inativar o membro e encerrar todos os vínculos ativos? O acesso de usuário, se existir, não será alterado.">@csrf @method('PATCH')<button class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50" type="submit">Inativar membro</button></form>
                    @endif
                </div>
            @endcan
        </header>

        @include('members._validation-errors')

        <div class="grid gap-7 lg:grid-cols-[minmax(0,1fr)_21rem]">
            <section class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="personal-title">
                <h2 class="text-xl font-semibold text-ink-950" id="personal-title">Dados pessoais</h2>
                <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold tracking-wide text-slate-500 uppercase">E-mail</dt><dd class="mt-1.5 text-sm text-ink-950">{{ $member->email ?: 'Não informado' }}</dd></div>
                    <div><dt class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Telefone</dt><dd class="mt-1.5 text-sm text-ink-950">{{ $member->phone ?: 'Não informado' }}</dd></div>
                    <div><dt class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Nascimento</dt><dd class="mt-1.5 text-sm text-ink-950">{{ $member->birth_date?->format('d/m/Y') ?? 'Não informado' }}</dd></div>
                    <div><dt class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Sexo</dt><dd class="mt-1.5 text-sm text-ink-950">{{ $member->sex?->label() ?? 'Não informado' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Endereço</dt><dd class="mt-1.5 text-sm leading-6 text-ink-950">@if ($member->street){{ $member->street }}{{ $member->number ? ', '.$member->number : '' }}{{ $member->neighborhood ? ' · '.$member->neighborhood : '' }}{{ $member->complement ? ' · '.$member->complement : '' }}<br>@endif{{ $member->city->name }} · {{ $member->city->state->abbreviation }}{{ $member->postal_code ? ' · CEP '.substr($member->postal_code, 0, 5).'-'.substr($member->postal_code, 5) : '' }}</dd></div>
                </dl>
            </section>

            <aside class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold tracking-widest text-slate-400 uppercase">Acesso ao sistema</p>
                @if ($member->user)
                    <h2 class="mt-3 font-semibold text-ink-950">Possui usuário</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ '@'.$member->user->username }}</p>
                    <div class="mt-3"><x-status-badge :status="$member->user->status" /></div>
                @else
                    <h2 class="mt-3 font-semibold text-ink-950">Sem usuário vinculado</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">O cadastro de membro não cria acesso ao sistema.</p>
                @endif
            </aside>
        </div>

        <section class="overflow-hidden rounded-3xl border border-stone-200 bg-white shadow-sm" aria-labelledby="memberships-title">
            <div class="border-b border-stone-200 p-6 sm:px-8"><h2 class="text-xl font-semibold text-ink-950" id="memberships-title">Vínculos com igrejas</h2><p class="mt-1 text-sm text-slate-500">A igreja principal identifica a referência atual do membro.</p></div>

            @can('manageMemberships', $member)
                @if ($member->status === App\Status::Active && $availableChurches->isNotEmpty())
                    <form class="grid gap-4 border-b border-stone-200 bg-stone-50/60 p-6 sm:grid-cols-[minmax(14rem,1fr)_12rem_auto] sm:px-8" method="POST" action="{{ route('members.memberships.store', $member) }}">
                        @csrf
                        <div><label class="mb-1.5 block text-xs font-semibold text-slate-600" for="church_id">Adicionar igreja</label><select class="block w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-sm" id="church_id" name="church_id" required><option value="">Selecione</option>@foreach ($availableChurches as $church)<option value="{{ $church->id }}">{{ $church->name }}</option>@endforeach</select></div>
                        <div><label class="mb-1.5 block text-xs font-semibold text-slate-600" for="joined_at">Data de entrada</label><input class="block w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-sm" id="joined_at" name="joined_at" type="date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required></div>
                        <div class="flex items-end"><button class="w-full rounded-xl bg-genesis-600 px-4 py-2.5 text-sm font-semibold text-white" type="submit">Vincular</button></div>
                    </form>
                @elseif ($member->status !== App\Status::Active)
                    <p class="border-b border-stone-200 bg-stone-50 px-6 py-4 text-sm text-slate-500 sm:px-8">Membros inativos não recebem novos vínculos.</p>
                @endif
            @endcan

            @if ($member->memberships->isEmpty())
                <p class="p-8 text-center text-sm text-slate-500">Nenhum vínculo registrado.</p>
            @else
                <div class="grid divide-y divide-stone-100">
                    @foreach ($member->memberships as $membership)
                        @php $isCurrent = $membership->status === App\Status::Active && $membership->ended_at === null; @endphp
                        <article class="grid gap-4 p-6 sm:px-8 lg:grid-cols-[minmax(0,1fr)_10rem_11rem_auto] lg:items-center">
                            <div><div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-ink-950">{{ $membership->church->name }}</h3>@if ($membership->is_primary)<span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Principal</span>@endif</div><p class="mt-1 text-xs text-slate-500">Entrada em {{ $membership->joined_at->format('d/m/Y') }}</p></div>
                            <div><p class="text-xs text-slate-500">Encerramento</p><p class="mt-1 text-sm text-slate-700">{{ $membership->ended_at?->format('d/m/Y') ?? '—' }}</p></div>
                            <div><x-status-badge :status="$membership->status" /></div>
                            @can('manageMemberships', $member)
                                @if ($isCurrent)
                                    <div class="flex flex-wrap justify-start gap-2 lg:justify-end">
                                        @if (! $membership->is_primary)
                                            <form method="POST" action="{{ route('members.memberships.primary', [$member, $membership]) }}">@csrf @method('PATCH')<button class="rounded-lg border border-stone-200 px-3 py-2 text-xs font-semibold text-genesis-700" type="submit">Tornar principal</button></form>
                                            <form method="POST" action="{{ route('members.memberships.end', [$member, $membership]) }}" data-confirm="Encerrar este vínculo?">@csrf @method('PATCH')<button class="rounded-lg border border-red-100 px-3 py-2 text-xs font-semibold text-red-700" type="submit">Encerrar</button></form>
                                        @elseif ($activeMemberships->count() > 1)
                                            <span class="text-xs text-slate-500">Escolha outra principal para encerrar.</span>
                                        @endif
                                    </div>
                                @endif
                            @endcan
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
