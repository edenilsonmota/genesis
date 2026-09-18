@extends('layouts.app')
@section('title', 'Auditoria')
@section('header', 'Auditoria')
@section('content')
@php
    $hasFilters = filled($filters['search'] ?? null) || filled($filters['action'] ?? null) || filled($filters['resource'] ?? null) || filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null);
@endphp
<div class="mx-auto grid max-w-7xl gap-7">
    <header>
        <p class="ui-page-kicker">Administração</p>
        <h1 class="mt-1 ui-page-title">Auditoria</h1>
        <p class="mt-2 ui-page-copy">
            {{ $church ? 'Atividades relacionadas à igreja '.$church->name.'.' : 'Visão consolidada das atividades realizadas em todo o sistema.' }}
        </p>
    </header>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumo da auditoria">
        @foreach ([
            ['Registros encontrados', $summary['records'], 'text-brand-primary'],
            ['Atividades hoje', $summary['today'], 'text-info'],
            ['Responsáveis', $summary['actors'], 'text-success'],
            ['Tipos de registro', $summary['resources'], 'text-warning'],
        ] as [$label, $value, $color])
            <article class="ui-card p-5">
                <p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold {{ $color }}">{{ number_format($value, 0, ',', '.') }}</p>
            </article>
        @endforeach
    </section>

    <section class="ui-card">
        <details class="rounded-t-3xl border-b border-border-default bg-surface-muted/60" @if($hasFilters) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 text-sm font-semibold text-text-primary marker:content-none">
                <span class="flex items-center gap-2">
                    <svg class="size-4 text-brand-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M7 12h10m-7 6h4" /></svg>
                    Filtros
                    @if ($hasFilters)<span class="rounded-full bg-brand-primary px-2 py-0.5 text-[10px] text-white">Ativos</span>@endif
                </span>
                <span class="text-xs font-medium text-text-secondary">Expandir ou recolher</span>
            </summary>
            <form class="grid gap-3 border-t border-border-default p-5 md:grid-cols-2 xl:grid-cols-4" method="GET">
                <div class="xl:col-span-2">
                    <label class="ui-label" for="audit-search">Busca</label>
                    <input class="ui-input" id="audit-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Responsável, ação, recurso ou ID">
                </div>
                <div>
                    <label class="ui-label" for="audit-date-from">Data inicial</label>
                    <input class="ui-input" id="audit-date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div>
                    <label class="ui-label" for="audit-date-to">Data final</label>
                    <input class="ui-input" id="audit-date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="xl:col-span-2">
                    <label class="ui-label" for="audit-action">Ação</label>
                    <select class="ui-select" id="audit-action" name="action">
                        <option value="">Todas as ações</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $actionLabels[$action] ?? str($action)->replace(['.', '_'], ' ')->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="xl:col-span-2">
                    <label class="ui-label" for="audit-resource">Tipo de registro</label>
                    <select class="ui-select" id="audit-resource" name="resource">
                        <option value="">Todos os tipos</option>
                        @foreach ($resources as $resource)
                            <option value="{{ $resource }}" @selected(($filters['resource'] ?? '') === $resource)>{{ $resourceLabels[$resource] ?? str($resource)->replace('_', ' ')->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap items-end gap-2 xl:col-span-4">
                    <button class="ui-button-primary" type="submit">Aplicar filtros</button>
                    <a class="ui-button-outline" href="{{ route('audit.index') }}">Limpar</a>
                </div>
            </form>
        </details>

        <div class="divide-y divide-border-default">
            <div class="hidden bg-surface-muted px-5 py-3 text-xs font-semibold tracking-wide text-text-secondary uppercase xl:grid xl:grid-cols-[9rem_minmax(13rem,1.35fr)_minmax(10rem,1fr)_minmax(10rem,1fr)_8rem] xl:items-center xl:gap-4">
                <span>Data e hora</span>
                <span>Atividade</span>
                <span>Registro</span>
                <span>Responsável</span>
                <span>Detalhes</span>
            </div>

            @forelse ($logs as $log)
                @php
                    $actionLabel = $actionLabels[$log->action] ?? str($log->action)->replace(['.', '_'], ' ')->headline();
                    $resourceLabel = $resourceLabels[$log->resource] ?? str($log->resource)->replace('_', ' ')->headline();
                    $scopeLabel = $log->scope_id ? ($scopeLabels[$log->scope_id] ?? ucfirst($log->scope_type ?? 'escopo')) : ($church?->name ?? 'Visão geral');
                    $isDanger = str_contains($log->action, 'inactivated') || str_contains($log->action, 'cancelled') || str_contains($log->action, 'reversed');
                    $isSuccess = str_contains($log->action, 'created') || str_contains($log->action, 'assigned') || str_contains($log->action, 'settled');
                @endphp
                <article class="grid gap-4 p-5 xl:grid-cols-[9rem_minmax(13rem,1.35fr)_minmax(10rem,1fr)_minmax(10rem,1fr)_8rem] xl:items-center">
                    <div>
                        <time class="font-medium text-text-primary" datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('d/m/Y') }}</time>
                        <p class="mt-1 text-xs text-text-secondary">{{ $log->created_at->format('H:i:s') }}</p>
                    </div>
                    <div class="min-w-0">
                        <span @class([
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-danger-soft text-danger' => $isDanger,
                            'bg-success-soft text-success' => $isSuccess && ! $isDanger,
                            'bg-brand-primary-soft text-brand-primary' => ! $isDanger && ! $isSuccess,
                        ])>{{ $actionLabel }}</span>
                        <p class="mt-2 truncate text-xs text-text-secondary" title="{{ $log->action }}">{{ $log->action }}</p>
                    </div>
                    <div class="min-w-0">
                        <p class="font-medium text-text-primary">{{ $resourceLabel }}</p>
                        <p class="mt-1 truncate text-xs text-text-secondary" title="{{ $log->record_id }}">{{ $log->record_id ? 'ID '.$log->record_id : 'Sem identificador' }}</p>
                        <p class="mt-1 truncate text-xs text-text-secondary">{{ $scopeLabel }}</p>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-medium text-text-primary">{{ $log->actor_name ?: 'Sistema' }}</p>
                        <p class="mt-1 truncate text-xs text-text-secondary">{{ $log->actor_username ? '@'.$log->actor_username : 'Ação automatizada' }}</p>
                    </div>
                    <details class="relative open:z-30">
                        <summary class="ui-button-outline w-fit cursor-pointer list-none px-3 py-2 text-xs marker:content-none">Visualizar</summary>
                        <div class="mt-3 grid gap-3 rounded-2xl border border-border-default bg-surface-muted p-4 text-xs xl:absolute xl:right-0 xl:z-20 xl:mt-2 xl:w-[30rem] xl:shadow-xl">
                            <dl class="grid gap-2 sm:grid-cols-2">
                                <div><dt class="font-semibold text-text-secondary">Rota</dt><dd class="mt-1 break-all text-text-primary">{{ $log->route ?: 'Não informada' }}</dd></div>
                                <div><dt class="font-semibold text-text-secondary">Endereço IP</dt><dd class="mt-1 text-text-primary">{{ $log->ip_address ?: 'Não informado' }}</dd></div>
                            </dl>
                            <div>
                                <p class="font-semibold text-text-secondary">Dados registrados</p>
                                @if (filled($log->details))
                                    <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-surface-card p-3 font-mono text-[11px] leading-5 text-text-primary">{{ json_encode($log->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                @else
                                    <p class="mt-1 text-text-primary">Nenhum detalhe adicional.</p>
                                @endif
                            </div>
                        </div>
                    </details>
                </article>
            @empty
                <div class="grid justify-items-center gap-3 px-6 py-14 text-center">
                    <span class="grid size-12 place-items-center rounded-2xl bg-brand-primary-soft text-brand-primary" aria-hidden="true">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.6 2.8 8.1 7 10 4.2-1.9 7-5.4 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
                    </span>
                    <p class="max-w-xl text-sm leading-6 text-text-secondary">Nenhuma atividade foi encontrada neste escopo e nos filtros informados.</p>
                </div>
            @endforelse
        </div>

        @if ($logs->hasPages())
            <div class="border-t border-border-default p-4">{{ $logs->links() }}</div>
        @endif
    </section>
</div>
@endsection
