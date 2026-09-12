@extends('layouts.app')

@section('title', 'Editar membro')
@section('header', 'Editar membro')

@section('content')
    <div class="mx-auto grid max-w-4xl gap-6">
        <div><a class="text-sm font-semibold text-genesis-700" href="{{ route('members.show', $member) }}">← Voltar ao membro</a><h1 class="mt-3 text-3xl font-semibold tracking-tight text-ink-950">Editar {{ $member->name }}</h1><p class="mt-2 text-sm text-slate-500">Os vínculos com igrejas são administrados na página do membro.</p></div>
        @include('members._validation-errors')
        <form class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8" method="POST" action="{{ route('members.update', $member) }}">
            @csrf
            @method('PUT')
            @include('members._fields')
            <div class="mt-7 flex flex-col-reverse gap-3 border-t border-stone-200 pt-5 sm:flex-row sm:justify-end"><a class="rounded-xl border border-stone-300 px-5 py-2.5 text-center text-sm font-semibold text-slate-700" href="{{ route('members.show', $member) }}">Cancelar</a><button class="rounded-xl bg-genesis-600 px-5 py-2.5 text-sm font-semibold text-white" type="submit">Salvar alterações</button></div>
        </form>
    </div>
@endsection
