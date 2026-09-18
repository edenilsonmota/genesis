@extends('layouts.app')
@section('title', 'Agenda')
@section('header', 'Agenda')
@section('content')
<div class="mx-auto grid max-w-7xl gap-7" data-calendar-shell>
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="ui-page-kicker">Planejamento</p>
            <h1 class="mt-1 ui-page-title">Agenda</h1>
            <p class="mt-2 ui-page-copy">{{ $church ? 'Eventos da igreja '.$church->name.' e eventos gerais da área.' : 'Visão consolidada dos eventos de todas as igrejas.' }}</p>
        </div>
        @if ($canCreate)
            <a class="ui-button-primary" href="{{ route('calendar.events.create', $church ? ['church_id' => $church->id] : []) }}">Novo evento</a>
        @endif
    </header>

    <div class="ui-alert-error" data-calendar-feedback hidden role="status"></div>

    <section class="ui-card grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_15rem] lg:items-start">
        <form class="grid gap-3 sm:grid-cols-3" data-calendar-filters>
            <div>
                <label class="ui-label" for="calendar-type">Tipo</label>
                <select class="ui-select" id="calendar-type" name="type">
                    <option value="">Todos os tipos</option>
                    @foreach ($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="ui-label" for="calendar-status">Status</label>
                <select class="ui-select" id="calendar-status" name="status">
                    <option value="">Todos os status</option>
                    @foreach ($statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="ui-label" for="calendar-department">Departamento</label>
                <select class="ui-select" id="calendar-department" name="department_id">
                    <option value="">Todos os departamentos</option>
                    @foreach ($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach
                </select>
            </div>
            <div class="flex flex-wrap gap-2 sm:col-span-3">
                <button class="ui-button-secondary" type="submit">Aplicar filtros</button>
                <button class="ui-button-outline" type="button" data-calendar-filter-clear>Limpar</button>
            </div>
        </form>
        <div>
            <label class="ui-label" for="calendar-view">Visualização</label>
            <select class="ui-select" id="calendar-view" data-calendar-view>
                <option value="multiMonthYear">Ano</option>
                <option value="dayGridMonth" selected>Mês</option>
                <option value="timeGridWeek">Semana</option>
                <option value="timeGridDay">Dia</option>
                <option value="listUpcoming">Próximos eventos</option>
            </select>
        </div>
    </section>

    <section class="ui-card min-w-0 overflow-hidden p-3 sm:p-5">
        <div
            data-calendar
            data-feed-url="{{ route('calendar.feed') }}"
            data-create-url="{{ route('calendar.events.create') }}"
            data-can-create="{{ $canCreate ? 'true' : 'false' }}"
            aria-label="Calendário de eventos"
        ></div>
    </section>

    <section class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-text-secondary" aria-label="Legenda da agenda">
        <span class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-brand-primary"></span>Confirmado</span>
        <span class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-warning"></span>Rascunho</span>
        <span class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-danger"></span>Cancelado</span>
        @if ($canCreate)<span>Arraste eventos autorizados para reagendar ou selecione um período para criar.</span>@endif
    </section>
</div>
@endsection
