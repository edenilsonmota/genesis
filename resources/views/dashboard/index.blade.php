@extends('layouts.app')

@section('title', 'Visão geral')
@section('header', 'Visão geral')

@section('content')
<div class="mx-auto grid max-w-7xl gap-6" data-dashboard-overview>
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="ui-page-kicker">Painel administrativo</p>
            <h1 class="mt-1 ui-page-title">Visão geral</h1>
            <p class="mt-2 ui-page-copy">
                Olá, {{ auth()->user()->display_name }}. {{ $overview['is_consolidated'] ? 'Acompanhe toda a estrutura da organização.' : 'Acompanhe a estrutura de '.$overview['scope'].'.' }}
            </p>
        </div>
        <span class="inline-flex w-fit items-center gap-2 rounded-full border border-border-default bg-surface-card px-3.5 py-2 text-sm font-semibold text-text-secondary shadow-sm">
            <span class="size-2 rounded-full bg-success" aria-hidden="true"></span>{{ $overview['scope'] }}
        </span>
    </header>

    @php
        $indicators = [
            ['Igrejas ativas', $overview['indicators']['churches']['value'], '+'.$overview['indicators']['churches']['new_this_month'].' neste mês', 'text-brand-primary', 'bg-brand-primary/10'],
            ['Membros ativos', $overview['indicators']['members']['value'], '+'.$overview['indicators']['members']['new_this_month'].' neste mês', 'text-success', 'bg-success-soft'],
            ['Usuários com acesso', $overview['indicators']['users']['value'], 'Contas ativas e autorizadas', 'text-info', 'bg-info-soft'],
            ['Cargos e departamentos', $overview['indicators']['structure']['value'], $overview['indicators']['structure']['positions'].' cargos · '.$overview['indicators']['structure']['departments'].' departamentos', 'text-warning', 'bg-warning-soft'],
        ];
    @endphp

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores administrativos">
        @foreach ($indicators as [$label, $value, $detail, $color, $background])
            <article class="ui-card overflow-hidden p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">{{ $label }}</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight {{ $color }}">{{ number_format($value, 0, ',', '.') }}</p>
                    </div>
                    <span class="grid size-10 shrink-0 place-items-center rounded-2xl {{ $background }} {{ $color }}" aria-hidden="true">
                        @if ($loop->index === 0)
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20h16M6 20V9l6-5 6 5v11M9 20v-5h6v5" /></svg>
                        @elseif ($loop->index === 1)
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-4A4.5 4.5 0 0 0 3 18.5V20m10-10a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Zm4-4a3 3 0 0 1 0 6" /></svg>
                        @elseif ($loop->index === 2)
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/></svg>
                        @else
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v14H4zM8 9h3v3H8zm5 0h3m-3 4h3M8 15h8"/></svg>
                        @endif
                    </span>
                </div>
                <p class="mt-3 text-sm text-text-secondary">{{ $detail }}</p>
            </article>
        @endforeach
    </section>

    @php $today = \Carbon\CarbonImmutable::today(config('genesis.calendar.timezone'))->locale('pt_BR'); @endphp
    <section class="grid gap-5 lg:grid-cols-2">
        <article class="ui-card overflow-hidden">
            <div class="flex items-start justify-between gap-4 border-b border-border-default px-5 py-4 sm:px-6">
                <div><h2 class="text-lg font-semibold text-text-primary">Aniversariantes de hoje</h2><p class="mt-1 text-sm text-text-secondary">Membros ativos{{ $overview['is_consolidated'] ? ' da área' : ' desta igreja' }}.</p></div>
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-warning-soft text-warning" aria-hidden="true"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM8 10h.01M16 10h.01M8.5 15c1 .9 2.17 1.35 3.5 1.35S14.5 15.9 15.5 15"/></svg></span>
            </div>
            @forelse ($overview['birthdays'] as $birthday)
                <div class="flex items-center gap-3 border-b border-border-default px-5 py-3.5 last:border-b-0 sm:px-6">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-warning-soft text-sm font-bold text-warning" aria-hidden="true">{{ str($birthday['name'])->substr(0, 1)->upper() }}</span>
                    <div class="min-w-0 flex-1"><p class="truncate font-semibold text-text-primary">{{ $birthday['name'] }}</p><p class="mt-0.5 text-xs text-text-secondary">Completa {{ $birthday['age'] }} {{ $birthday['age'] === 1 ? 'ano' : 'anos' }}</p></div>
                    <span class="text-lg" aria-hidden="true">🎉</span>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-text-secondary">Não há aniversariantes neste escopo hoje.</div>
            @endforelse
        </article>

        <article class="ui-card overflow-hidden">
            <div class="flex items-start justify-between gap-4 border-b border-border-default px-5 py-4 sm:px-6">
                <div><h2 class="text-lg font-semibold text-text-primary">Agenda de hoje</h2><p class="mt-1 text-sm text-text-secondary">{{ $today->translatedFormat('l, d \\d\\e F') }}.</p></div>
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-brand-primary-soft text-center text-brand-primary" aria-hidden="true"><span class="text-base font-bold leading-none">{{ $today->format('d') }}</span><span class="mt-0.5 text-[9px] font-bold leading-none uppercase">{{ $today->translatedFormat('M') }}</span></span>
            </div>
            @if (! $overview['can_view_today_events'])
                <div class="px-6 py-10 text-center text-sm text-text-secondary">Você não possui permissão para consultar os eventos deste escopo.</div>
            @elseif ($overview['today_events'])
                <ol class="divide-y divide-border-default">
                    @foreach ($overview['today_events'] as $event)
                        <li><a class="flex gap-3 px-5 py-3.5 transition hover:bg-surface-muted sm:px-6" href="{{ $event['url'] }}"><span class="mt-1 size-2.5 shrink-0 rounded-full {{ $event['status'] === 'Cancelado' ? 'bg-danger' : ($event['status'] === 'Rascunho' ? 'bg-warning' : 'bg-brand-primary') }}" aria-hidden="true"></span><div class="min-w-0 flex-1"><div class="flex items-center justify-between gap-3"><p class="truncate font-semibold text-text-primary">{{ $event['title'] }}</p><span class="shrink-0 text-xs font-semibold text-brand-primary">{{ $event['time'] }}</span></div><p class="mt-1 truncate text-xs text-text-secondary">{{ $event['type'] }} · {{ $event['scope'] }} · {{ $event['status'] }}</p></div></a></li>
                    @endforeach
                </ol>
            @else
                <div class="px-6 py-10 text-center text-sm text-text-secondary">Nenhum evento programado para hoje.</div>
            @endif
            @if ($overview['can_view_today_events'])
                <div class="border-t border-border-default px-5 py-3 text-right sm:px-6"><a class="text-sm font-semibold text-brand-primary hover:text-brand-primary-hover" href="{{ route('calendar.index') }}">Abrir agenda →</a></div>
            @endif
        </article>
    </section>

    <section class="grid gap-5 lg:grid-cols-3">
        <article class="ui-card min-w-0 overflow-hidden lg:col-span-2">
            <div class="border-b border-border-default px-5 py-4 sm:px-6">
                <h2 class="text-lg font-semibold text-text-primary">Últimas atividades</h2>
                <p class="mt-1 text-sm text-text-secondary">Alterações administrativas recentes neste escopo.</p>
            </div>
            @if ($overview['activities'])
                <ol class="divide-y divide-border-default">
                    @foreach ($overview['activities'] as $activity)
                        <li class="flex gap-3 px-5 py-4 sm:px-6">
                            <span class="mt-1.5 size-2.5 shrink-0 rounded-full bg-brand-primary ring-4 ring-brand-primary/10" aria-hidden="true"></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-text-primary">{{ $activity['label'] }}@if($activity['subject']) <span class="font-normal text-text-secondary">· {{ $activity['subject'] }}</span>@endif</p>
                                <p class="mt-1 text-xs text-text-secondary">Por {{ $activity['actor'] }} · {{ \Carbon\Carbon::parse($activity['occurred_at'])->locale('pt_BR')->diffForHumans() }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @else
                <div class="px-6 py-12 text-center text-sm text-text-secondary">Nenhuma atividade administrativa recente neste escopo.</div>
            @endif
        </article>

        <article class="ui-card p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-text-primary">Ações rápidas</h2>
            <p class="mt-1 text-sm text-text-secondary">Atalhos disponíveis conforme suas permissões.</p>
            <div class="mt-5 grid gap-3">
                @can('create', App\Models\Member::class)
                    <a class="flex items-center justify-between rounded-2xl border border-border-default px-4 py-3 font-semibold text-text-primary transition hover:border-brand-cyan hover:bg-surface-muted" href="{{ route('members.create') }}"><span>Novo membro</span><span class="text-brand-primary" aria-hidden="true">→</span></a>
                @endcan
                @can('create', App\Models\Church::class)
                    <a class="flex items-center justify-between rounded-2xl border border-border-default px-4 py-3 font-semibold text-text-primary transition hover:border-brand-cyan hover:bg-surface-muted" href="{{ route('organization.index', ['panel' => 'create-church']) }}"><span>Nova igreja</span><span class="text-brand-primary" aria-hidden="true">→</span></a>
                @endcan
                @can('create', App\Models\User::class)
                    <a class="flex items-center justify-between rounded-2xl border border-border-default px-4 py-3 font-semibold text-text-primary transition hover:border-brand-cyan hover:bg-surface-muted" href="{{ route('users.create') }}"><span>Criar usuário</span><span class="text-brand-primary" aria-hidden="true">→</span></a>
                @endcan
                @can('viewAny', App\Models\Position::class)
                    <a class="flex items-center justify-between rounded-2xl border border-border-default px-4 py-3 font-semibold text-text-primary transition hover:border-brand-cyan hover:bg-surface-muted" href="{{ route('positions.index') }}"><span>Gerenciar cargos</span><span class="text-brand-primary" aria-hidden="true">→</span></a>
                @endcan
            </div>
        </article>
    </section>

    <section class="grid gap-5 lg:grid-cols-3">
        <article class="ui-card min-w-0 p-5 sm:p-6 lg:col-span-2">
            <h2 class="text-lg font-semibold text-text-primary">Membros por igreja</h2>
            <p class="mt-1 text-sm text-text-secondary">Quantidade de membros ativos com vínculo vigente.</p>
            @php $membersChartHeight = max(320, min(640, count($overview['members_by_church']) * 46 + 100)); @endphp
            <div class="mt-4 w-full" style="height: {{ $membersChartHeight }}px" data-dashboard-members-chart role="img" aria-label="Membros ativos por igreja"></div>
        </article>

        <article class="ui-card p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-text-primary">Status da estrutura</h2>
            <p class="mt-1 text-sm text-text-secondary">Verificação rápida da configuração administrativa.</p>
            <dl class="mt-5 grid gap-4">
                <div class="flex items-center justify-between gap-4 rounded-2xl bg-surface-muted px-4 py-3">
                    <div><dt class="font-semibold text-text-primary">Área configurada</dt><dd class="mt-0.5 text-xs text-text-secondary">Estrutura organizacional principal</dd></div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $overview['structure']['area_configured'] ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }}">{{ $overview['structure']['area_configured'] ? 'Pronto' : 'Pendente' }}</span>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl bg-surface-muted px-4 py-3">
                    <div><dt class="font-semibold text-text-primary">Igrejas cadastradas</dt><dd class="mt-0.5 text-xs text-text-secondary">Igrejas ativas no escopo</dd></div>
                    <span class="text-xl font-semibold text-brand-primary">{{ $overview['structure']['churches'] }}</span>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl bg-surface-muted px-4 py-3">
                    <div><dt class="font-semibold text-text-primary">Permissões definidas</dt><dd class="mt-0.5 text-xs text-text-secondary">Cargos com regras configuradas</dd></div>
                    <span class="text-xl font-semibold {{ $overview['structure']['permissions'] > 0 ? 'text-success' : 'text-warning' }}">{{ $overview['structure']['permissions'] }}</span>
                </div>
            </dl>
        </article>
    </section>

    <script id="dashboard-overview-data" type="application/json">@json(['members_by_church' => $overview['members_by_church']])</script>
</div>
@endsection
