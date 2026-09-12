@extends('layouts.app')
@section('title', 'Nova categoria financeira')
@section('header', 'Nova categoria financeira')
@section('content')
<div class="mx-auto grid max-w-2xl gap-6">
    <a class="text-sm font-semibold text-brand-primary hover:text-brand-primary-hover" href="{{ route('finance.categories.index') }}">← Voltar</a>
    <header><p class="ui-page-kicker">Financeiro</p><h1 class="mt-1 ui-page-title">Nova categoria financeira</h1><p class="mt-2 ui-page-copy">Disponível para todas as igrejas de {{ $area->name }}.</p></header>
    <form class="ui-card grid gap-5 p-6 sm:p-8" method="POST" action="{{ route('finance.categories.store') }}">
        @csrf
        @include('finance.categories._fields')
        <div class="flex flex-wrap justify-end gap-3"><a class="ui-button-outline" href="{{ route('finance.categories.index') }}">Cancelar</a><button class="ui-button-primary" type="submit">Criar categoria</button></div>
    </form>
</div>
@endsection
