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
