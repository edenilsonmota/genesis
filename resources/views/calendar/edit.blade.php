@extends('layouts.app')
@section('title', 'Editar evento')
@section('header', 'Editar evento')
@section('content')
<div class="mx-auto grid max-w-4xl gap-6">
    <a class="text-sm font-semibold text-brand-primary" href="{{ route('calendar.events.show', $calendarEvent) }}">← Voltar ao evento</a>
    <header><p class="ui-page-kicker">Agenda</p><h1 class="mt-1 ui-page-title">Editar evento</h1><p class="mt-2 ui-page-copy">{{ $calendarEvent->title }}</p></header>
    <form class="ui-card grid gap-6 p-6 sm:p-8" method="POST" action="{{ route('calendar.events.update', $calendarEvent) }}" data-calendar-event-form>
        @csrf @method('PUT')
        @include('calendar._form')
        <div class="flex flex-wrap justify-end gap-3 border-t border-border-default pt-6"><a class="ui-button-outline" href="{{ route('calendar.events.show', $calendarEvent) }}">Cancelar</a><button class="ui-button-primary" type="submit">Salvar alterações</button></div>
    </form>
    @include('calendar._quick-event-type-modal')
</div>
@endsection
