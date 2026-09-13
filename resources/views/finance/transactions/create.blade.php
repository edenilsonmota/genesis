@extends('layouts.app')
@section('title', 'Nova movimentação')
@section('header', 'Movimentações')
@section('content')
<div class="mx-auto grid max-w-5xl gap-7">
    <header>
        <p class="ui-page-kicker">Financeiro</p>
        <h1 class="mt-1 ui-page-title">Nova movimentação</h1>
        <p class="mt-2 ui-page-copy">Registre uma entrada, saída ou transferência. Apenas lançamentos liquidados afetam os saldos.</p>
    </header>

    @if ($accounts->isEmpty())
        <section class="rounded-2xl border border-warning/20 bg-warning-soft p-5 text-sm text-warning" role="status">
            <p class="font-semibold">Nenhuma conta ativa está disponível no seu escopo de escrita.</p>
            <p class="mt-1">Cadastre ou ative as contas financeiras antes de criar uma movimentação.</p>
            @can('viewAny', App\Models\FinancialAccount::class)
                <a class="mt-3 inline-flex font-semibold text-brand-primary hover:text-brand-primary-hover" href="{{ route('finance.accounts.index') }}">Ir para contas financeiras →</a>
            @endcan
        </section>
    @endif

    <form class="ui-card grid gap-6 p-6 sm:p-8" method="POST" action="{{ match ($selectedType) { App\Enums\FinancialTransactionType::Expense => route('finance.transactions.expenses.store'), App\Enums\FinancialTransactionType::Transfer => route('finance.transactions.transfers.store'), default => route('finance.transactions.income.store') } }}" data-financial-transaction-form data-income-action="{{ route('finance.transactions.income.store') }}" data-expense-action="{{ route('finance.transactions.expenses.store') }}" data-transfer-action="{{ route('finance.transactions.transfers.store') }}">
        @csrf
        @include('finance.transactions._fields')
        <div class="flex flex-wrap justify-end gap-3 border-t border-border-default pt-6">
            <a class="ui-button-outline" href="{{ route('finance.transactions.index') }}">Cancelar</a>
            <button class="ui-button-primary" type="submit" @disabled($accounts->isEmpty())>Salvar movimentação</button>
        </div>
    </form>
</div>
@endsection
