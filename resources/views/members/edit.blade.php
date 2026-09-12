@extends('layouts.app')

@section('title', 'Editar membro')
@section('header', 'Editar membro')

@section('content')
    <div class="mx-auto grid max-w-4xl gap-6">
        <div><a class="text-sm font-semibold text-brand-primary" href="{{ route('members.show', $member) }}">← Voltar ao membro</a><h1 class="mt-3 ui-page-title">Editar {{ $member->name }}</h1><p class="mt-2 ui-page-copy">Os vínculos com igrejas são administrados na página do membro.</p></div>
        @include('members._validation-errors')
        <form class="ui-card p-6 sm:p-8" method="POST" action="{{ route('members.update', $member) }}">
            @csrf
            @method('PUT')
            @include('members._fields')
            <div class="mt-7 flex flex-col-reverse gap-3 border-t border-border-default pt-5 sm:flex-row sm:justify-end"><a class="ui-button-outline px-5" href="{{ route('members.show', $member) }}">Cancelar</a><button class="ui-button-primary px-5" type="submit">Salvar alterações</button></div>
        </form>
    </div>
@endsection
