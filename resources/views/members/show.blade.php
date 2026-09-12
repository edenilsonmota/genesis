@extends('layouts.app')

@section('title', $member->name)
@section('header', 'Detalhes do membro')

@section('content')
    @php
        $cpf = substr($member->cpf, 0, 3).'.'.substr($member->cpf, 3, 3).'.'.substr($member->cpf, 6, 3).'-'.substr($member->cpf, 9);
        $activeMemberships = $member->memberships->filter(fn ($membership) => $membership->status === App\Status::Active && $membership->ended_at === null);
        $positionAssignments = $member->memberships->flatMap(fn ($membership) => $membership->positionAssignments);
    @endphp
    <div class="mx-auto grid max-w-6xl gap-7">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><a class="text-sm font-semibold text-brand-primary" href="{{ route('members.index') }}">← Voltar para membros</a><div class="mt-3 flex flex-wrap items-center gap-3"><h1 class="ui-page-title">{{ $member->name }}</h1><x-status-badge :status="$member->status" /></div><p class="mt-2 text-sm text-text-secondary">CPF {{ $cpf }}</p></div>
            @can('update', $member)
                <div class="flex flex-wrap gap-2">
                    <a class="ui-button-outline" href="{{ route('members.edit', $member) }}">Editar dados</a>
                    @if ($member->status === App\Status::Active)
                        <form method="POST" action="{{ route('members.inactivate', $member) }}" data-confirm="Inativar o membro e encerrar todos os vínculos ativos? Um usuário vinculado perderá o acesso imediatamente.">@csrf @method('PATCH')<button class="ui-button-danger" type="submit">Inativar membro</button></form>
                    @endif
                </div>
            @endcan
        </header>

        @include('members._validation-errors')

        <div class="grid gap-7 lg:grid-cols-[minmax(0,1fr)_21rem]">
            <section class="ui-card p-6 sm:p-8" aria-labelledby="personal-title">
                <h2 class="text-xl font-semibold text-text-primary" id="personal-title">Dados pessoais</h2>
                <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">E-mail</dt><dd class="mt-1.5 text-sm text-text-primary">{{ $member->email ?: 'Não informado' }}</dd></div>
                    <div><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Telefone</dt><dd class="mt-1.5 text-sm text-text-primary">{{ $member->phone ?: 'Não informado' }}</dd></div>
                    <div><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Nascimento</dt><dd class="mt-1.5 text-sm text-text-primary">{{ $member->birth_date?->format('d/m/Y') ?? 'Não informado' }}</dd></div>
                    <div><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Sexo</dt><dd class="mt-1.5 text-sm text-text-primary">{{ $member->sex?->label() ?? 'Não informado' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Endereço</dt><dd class="mt-1.5 text-sm leading-6 text-text-primary">@if ($member->street){{ $member->street }}{{ $member->number ? ', '.$member->number : '' }}{{ $member->neighborhood ? ' · '.$member->neighborhood : '' }}{{ $member->complement ? ' · '.$member->complement : '' }}<br>@endif{{ $member->city->name }} · {{ $member->city->state->abbreviation }}{{ $member->postal_code ? ' · CEP '.substr($member->postal_code, 0, 5).'-'.substr($member->postal_code, 5) : '' }}</dd></div>
                </dl>
            </section>

            <aside class="ui-card p-6">
                <p class="text-xs font-semibold tracking-widest text-text-disabled uppercase">Acesso ao sistema</p>
                @if ($member->user)
                    <h2 class="mt-3 font-semibold text-text-primary">Possui usuário</h2>
                    <p class="mt-2 text-sm text-text-secondary">{{ '@'.$member->user->username }}</p>
                    <div class="mt-3"><x-status-badge :status="$member->user->status" /></div>
                @else
                    <h2 class="mt-3 font-semibold text-text-primary">Sem usuário vinculado</h2>
                    @if ($hasEligibleAccessPosition)
                        <p class="mt-2 text-sm leading-6 text-text-secondary">Este membro já possui cargo que concede acesso e pode receber uma credencial.</p>
                        @can('create', App\Models\User::class)<a class="mt-4 inline-flex text-sm font-semibold text-brand-primary" href="{{ route('users.create', ['member_id' => $member->id]) }}">Criar usuário →</a>@endcan
                    @else
                        <p class="mt-2 text-sm leading-6 text-text-secondary">Este membro ainda não possui um cargo que conceda acesso ao sistema. Vincule um cargo com acesso antes de criar o usuário.</p>
                        <a class="mt-4 inline-flex text-sm font-semibold text-brand-primary" href="#positions-title">Ir para Cargos ↓</a>
                    @endif
                @endif
            </aside>
        </div>

        <section class="ui-card overflow-hidden" aria-labelledby="memberships-title">
            <div class="border-b border-border-default p-6 sm:px-8"><h2 class="text-xl font-semibold text-text-primary" id="memberships-title">Vínculos com igrejas</h2><p class="mt-1 text-sm text-text-secondary">A igreja principal identifica a referência atual do membro.</p></div>

            @can('manageMemberships', $member)
                @if ($member->status === App\Status::Active && $availableChurches->isNotEmpty())
                    <form class="grid gap-4 border-b border-border-default bg-surface-muted/60 p-6 sm:grid-cols-[minmax(14rem,1fr)_12rem_auto] sm:px-8" method="POST" action="{{ route('members.memberships.store', $member) }}">
                        @csrf
                        <div><label class="ui-label text-xs" for="church_id">Adicionar igreja</label><select class="ui-select" id="church_id" name="church_id" required><option value="">Selecione</option>@foreach ($availableChurches as $church)<option value="{{ $church->id }}">{{ $church->name }}</option>@endforeach</select></div>
                        <div><label class="ui-label text-xs" for="joined_at">Data de entrada</label><input class="ui-input" id="joined_at" name="joined_at" type="date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required></div>
                        <div class="flex items-end"><button class="ui-button-primary w-full" type="submit">Vincular</button></div>
                    </form>
                @elseif ($member->status !== App\Status::Active)
                    <p class="border-b border-border-default bg-surface-muted px-6 py-4 text-sm text-text-secondary sm:px-8">Membros inativos não recebem novos vínculos.</p>
                @endif
            @endcan

            @if ($member->memberships->isEmpty())
                <p class="p-8 text-center text-sm text-text-secondary">Nenhum vínculo registrado.</p>
            @else
                <div class="grid divide-y divide-border-default/70">
                    @foreach ($member->memberships as $membership)
                        @php $isCurrent = $membership->status === App\Status::Active && $membership->ended_at === null; @endphp
                        <article class="grid gap-4 p-6 sm:px-8 lg:grid-cols-[minmax(0,1fr)_10rem_11rem_auto] lg:items-center">
                            <div><div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-text-primary">{{ $membership->church->name }}</h3>@if ($membership->is_primary)<span class="rounded-full bg-warning-soft px-2.5 py-1 text-xs font-semibold text-warning">Principal</span>@endif</div><p class="mt-1 text-xs text-text-secondary">Entrada em {{ $membership->joined_at->format('d/m/Y') }}</p></div>
                            <div><p class="text-xs text-text-secondary">Encerramento</p><p class="mt-1 text-sm text-text-secondary">{{ $membership->ended_at?->format('d/m/Y') ?? '—' }}</p></div>
                            <div><x-status-badge :status="$membership->status" /></div>
                            @can('manageMemberships', $member)
                                @if ($isCurrent)
                                    <div class="flex flex-wrap justify-start gap-2 lg:justify-end">
                                        @if (! $membership->is_primary)
                                            <form method="POST" action="{{ route('members.memberships.primary', [$member, $membership]) }}">@csrf @method('PATCH')<button class="ui-button-secondary rounded-lg px-3 py-2 text-xs" type="submit">Tornar principal</button></form>
                                            <form method="POST" action="{{ route('members.memberships.end', [$member, $membership]) }}" data-confirm="Encerrar este vínculo?">@csrf @method('PATCH')<button class="ui-button-danger rounded-lg px-3 py-2 text-xs" type="submit">Encerrar</button></form>
                                        @elseif ($activeMemberships->count() > 1)
                                            <span class="text-xs text-text-secondary">Escolha outra principal para encerrar.</span>
                                        @endif
                                    </div>
                                @endif
                            @endcan
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="ui-card overflow-hidden" aria-labelledby="positions-title">
            <div class="border-b border-border-default p-6 sm:px-8"><h2 class="text-xl font-semibold text-text-primary" id="positions-title">Cargos</h2><p class="mt-1 text-sm text-text-secondary">O departamento é derivado do cargo. O histórico permanece após o encerramento.</p></div>
            @can('managePositions', $member)
                @if($activeMemberships->isNotEmpty() && $positions->isNotEmpty())
                    <form class="grid gap-4 border-b border-border-default bg-surface-muted/60 p-6 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_12rem_auto] sm:px-8" method="POST" action="{{ route('members.positions.store', $member) }}">@csrf
                        <div><label class="ui-label text-xs" for="member_church_membership_id">Igreja</label><select class="ui-select" id="member_church_membership_id" name="member_church_membership_id" required><option value="">Selecione</option>@foreach($activeMemberships as $membership)<option value="{{ $membership->id }}">{{ $membership->church->name }}</option>@endforeach</select></div>
                        <div><label class="ui-label text-xs" for="position_id">Cargo</label><select class="ui-select" id="position_id" name="position_id" required><option value="">Selecione</option>@foreach($positions as $position)<option value="{{ $position->id }}">{{ $position->name }}{{ $position->department ? ' · '.$position->department->name : '' }}{{ $position->grants_system_access ? ' · concede acesso' : '' }}</option>@endforeach</select></div>
                        <div><label class="ui-label text-xs" for="started_at">Início</label><input class="ui-input" id="started_at" name="started_at" type="date" value="{{ today()->toDateString() }}" required></div>
                        <div class="flex items-end"><button class="ui-button-primary w-full">Atribuir</button></div>
                    </form>
                @endif
            @endcan
            <div class="divide-y divide-border-default">@forelse($positionAssignments->sortByDesc('started_at') as $assignment)<article class="grid gap-4 p-6 sm:px-8 lg:grid-cols-[minmax(0,1fr)_12rem_10rem_auto] lg:items-center"><div><h3 class="font-semibold text-text-primary">{{ $assignment->position->name }}</h3><p class="mt-1 text-sm text-text-secondary">{{ $member->memberships->firstWhere('id', $assignment->member_church_membership_id)?->church->name }} · {{ $assignment->position->department?->name ?? 'Sem departamento' }}{{ $assignment->position->grants_system_access ? ' · concede acesso' : '' }}</p></div><p class="text-sm text-text-secondary">{{ $assignment->started_at->format('d/m/Y') }} — {{ $assignment->ended_at?->format('d/m/Y') ?? 'atual' }}</p><x-status-badge :status="$assignment->status"/><div>@if($assignment->status === App\Status::Active && $assignment->ended_at === null)@can('managePositions', $member)<form class="flex gap-2" method="POST" action="{{ route('members.positions.end', [$member, $assignment]) }}" data-confirm="Encerrar este cargo? O acesso herdado pode ser revogado imediatamente.">@csrf @method('PATCH')<input class="ui-input w-36 py-2 text-xs" type="date" name="ended_at" min="{{ $assignment->started_at->toDateString() }}" value="{{ today()->toDateString() }}" required><button class="ui-button-danger px-3 py-2 text-xs">Encerrar</button></form>@endcan @endif</div></article>@empty<p class="p-8 text-center text-sm text-text-secondary">Nenhum cargo atribuído.</p>@endforelse</div>
        </section>
    </div>
@endsection
