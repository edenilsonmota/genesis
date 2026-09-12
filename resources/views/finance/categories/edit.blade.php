@extends('layouts.app')
@section('title', 'Editar categoria financeira')
@section('header', 'Editar categoria financeira')
@section('content')
<div class="mx-auto grid max-w-2xl gap-6">
    <a class="text-sm font-semibold text-brand-primary hover:text-brand-primary-hover" href="{{ route('finance.categories.index') }}">← Voltar</a>
    <header><p class="ui-page-kicker">Financeiro</p><h1 class="mt-1 ui-page-title">Editar categoria financeira</h1></header>
    <form class="ui-card grid gap-5 p-6 sm:p-8" method="POST" action="{{ route('finance.categories.update', $financialCategory) }}">
        @csrf
        @method('PUT')
        @include('finance.categories._fields')
        <div class="flex flex-wrap justify-end gap-3"><a class="ui-button-outline" href="{{ route('finance.categories.index') }}">Cancelar</a><button class="ui-button-primary" type="submit">Salvar alterações</button></div>
    </form>
</div>
@endsection
