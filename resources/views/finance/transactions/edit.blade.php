@extends('layouts.app')
@section('title', 'Editar movimentação')
@section('header', 'Movimentações')
@section('content')
<div class="mx-auto grid max-w-5xl gap-7">
    <header>
        <p class="ui-page-kicker">Financeiro</p>
        <h1 class="mt-1 ui-page-title">Editar {{ mb_strtolower($financialTransaction->type->label()) }}</h1>
        <p class="mt-2 ui-page-copy">Somente rascunhos e pendências manuais podem ter seus dados financeiros alterados.</p>
    </header>

    <form class="ui-card grid gap-6 p-6 sm:p-8" method="POST" action="{{ route('finance.transactions.update', $financialTransaction) }}" data-financial-transaction-form data-fixed-type="{{ $selectedType->value }}">
        @csrf
        @method('PUT')
        @include('finance.transactions._fields')
        <div class="flex flex-wrap justify-end gap-3 border-t border-border-default pt-6">
            <a class="ui-button-outline" href="{{ route('finance.transactions.show', $financialTransaction) }}">Cancelar</a>
            <button class="ui-button-primary" type="submit">Salvar alterações</button>
        </div>
    </form>
    @include('finance.transactions._quick-category-modal')
</div>
@endsection
