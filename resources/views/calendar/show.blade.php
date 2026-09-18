@extends('layouts.app')
@section('title', $calendarEvent->title)
@section('header', 'Evento')
@section('content')
@php
    $timezone = config('genesis.calendar.timezone');
    $start = $calendarEvent->starts_at->setTimezone($timezone);
    $end = $calendarEvent->ends_at->setTimezone($timezone);
    $displayEnd = $calendarEvent->all_day ? $end->subDay() : $end;
    $statusClass = match($calendarEvent->status) { App\Enums\CalendarEventStatus::Confirmed => 'bg-success-soft text-success', App\Enums\CalendarEventStatus::Draft => 'bg-warning-soft text-warning', App\Enums\CalendarEventStatus::Cancelled => 'bg-danger-soft text-danger' };
@endphp
<div class="mx-auto grid max-w-5xl gap-6">
    <a class="text-sm font-semibold text-brand-primary" href="{{ route('calendar.index') }}">← Voltar para a agenda</a>
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="ui-page-kicker">{{ $calendarEvent->eventType->name }}</p><h1 class="mt-1 ui-page-title">{{ $calendarEvent->title }}</h1><p class="mt-2 ui-page-copy">{{ $calendarEvent->church?->name ?? 'Toda a área · '.$calendarEvent->area->name }}</p></div>
        <div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">{{ $calendarEvent->status->label() }}</span>@can('update', $calendarEvent)<a class="ui-button-primary" href="{{ route('calendar.events.edit', $calendarEvent) }}">Editar evento</a>@endcan</div>
    </header>

    <section class="ui-card overflow-hidden">
        <dl class="grid sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-border-default p-5 sm:border-r"><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Início</dt><dd class="mt-2 font-semibold text-text-primary">{{ $start->format('d/m/Y') }}{{ $calendarEvent->all_day ? '' : ' às '.$start->format('H:i') }}</dd></div>
            <div class="border-b border-border-default p-5 lg:border-r"><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Término</dt><dd class="mt-2 font-semibold text-text-primary">{{ $displayEnd->format('d/m/Y') }}{{ $calendarEvent->all_day ? '' : ' às '.$displayEnd->format('H:i') }}</dd></div>
            <div class="border-b border-border-default p-5"><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Duração</dt><dd class="mt-2 font-semibold text-text-primary">{{ $calendarEvent->all_day ? 'Dia inteiro' : $start->diffForHumans($end, true) }}</dd></div>
            <div class="border-b border-border-default p-5 sm:border-r"><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Departamento</dt><dd class="mt-2 font-semibold text-text-primary">{{ $calendarEvent->department?->name ?? 'Sem departamento' }}</dd></div>
            <div class="border-b border-border-default p-5 lg:border-r"><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Responsável</dt><dd class="mt-2 font-semibold text-text-primary">{{ $calendarEvent->responsibleMember?->name ?? 'Sem responsável' }}</dd></div>
            <div class="border-b border-border-default p-5"><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Visibilidade</dt><dd class="mt-2 font-semibold text-text-primary">{{ $calendarEvent->visibility->label() }}</dd></div>
        </dl>
        <div class="grid gap-5 p-5 sm:grid-cols-2">
            <div><p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Local</p><p class="mt-2 text-sm text-text-primary">{{ $calendarEvent->location ?: 'Não informado' }}</p></div>
            <div><p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Criado por</p><p class="mt-2 text-sm text-text-primary">{{ $calendarEvent->createdByUser->display_name }} em {{ $calendarEvent->created_at->setTimezone($timezone)->format('d/m/Y H:i') }}</p></div>
            <div class="sm:col-span-2"><p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Descrição</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-text-primary">{{ $calendarEvent->description ?: 'Nenhuma descrição informada.' }}</p></div>
        </div>
    </section>

    @can('cancel', $calendarEvent)
        <section class="ui-card flex flex-col gap-4 border-danger/20 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="font-semibold text-text-primary">Cancelar evento</h2><p class="mt-1 text-sm text-text-secondary">O evento continuará no calendário e no histórico, identificado como cancelado.</p></div>
            <form method="POST" action="{{ route('calendar.events.cancel', $calendarEvent) }}" data-confirm="Tem certeza que deseja cancelar este evento? Ele será preservado no histórico e não poderá mais ser editado ou reagendado.">@csrf @method('PATCH')<button class="ui-button-danger" type="submit">Cancelar evento</button></form>
        </section>
    @endcan
</div>
@endsection
