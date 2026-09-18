@if ($errors->any())
    <div class="ui-alert-error" role="alert">
        <p class="font-semibold">Revise os campos informados.</p>
        <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@php
    $editing = isset($financialTransaction);
    $typeValue = old('transaction_type', $selectedType->value);
    $standardMovement = $editing && $financialTransaction->type !== App\Enums\FinancialTransactionType::Transfer ? $financialTransaction->movements->first() : null;
    $sourceMovement = $editing ? $financialTransaction->movements->firstWhere('direction', App\Enums\FinancialMovementDirection::Outflow) : null;
    $destinationMovement = $editing ? $financialTransaction->movements->firstWhere('direction', App\Enums\FinancialMovementDirection::Inflow) : null;
    $competenceMonth = old('competence_month_number', $editing && $financialTransaction->competence_month ? $financialTransaction->competence_month->month : '');
    $competenceYear = old('competence_year', $editing && $financialTransaction->competence_month ? $financialTransaction->competence_month->year : '');
    $months = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
@endphp

@unless ($editing)
    <fieldset>
        <legend class="ui-label">Tipo da movimentação</legend>
        <div class="grid gap-3 sm:grid-cols-3" data-transaction-type-options>
            @foreach ([App\Enums\FinancialTransactionType::Income, App\Enums\FinancialTransactionType::Expense, App\Enums\FinancialTransactionType::Transfer] as $type)
                <button @class([
                    'rounded-2xl border px-4 py-4 text-left transition',
                    'border-brand-primary bg-brand-primary-soft text-brand-primary' => $typeValue === $type->value,
                    'border-border-default bg-surface-card text-text-secondary hover:border-brand-sky hover:bg-surface-muted' => $typeValue !== $type->value,
                ]) type="button" data-transaction-type="{{ $type->value }}" aria-pressed="{{ $typeValue === $type->value ? 'true' : 'false' }}">
                    <span class="block font-semibold">{{ $type->label() }}</span>
                    <span class="mt-1 block text-xs">{{ match ($type) { App\Enums\FinancialTransactionType::Income => 'Valor recebido em uma conta.', App\Enums\FinancialTransactionType::Expense => 'Valor pago por uma conta.', default => 'Movimento atômico entre duas contas.' } }}</span>
                </button>
            @endforeach
        </div>
    </fieldset>
@else
    <div class="rounded-2xl bg-surface-muted p-4 text-sm text-text-secondary">
        Tipo: <strong class="text-text-primary">{{ $financialTransaction->type->label() }}</strong> · Status atual: <strong class="text-text-primary">{{ $financialTransaction->status->label() }}</strong>
    </div>
@endunless

<section class="grid gap-5 rounded-2xl border border-border-default/80 bg-surface-card p-5 sm:grid-cols-2 sm:p-6" data-standard-account-fields @if($typeValue === 'transfer') hidden @endif>
    <div class="sm:col-span-2">
        <label class="ui-label" for="account_id" data-account-label>{{ $typeValue === 'expense' ? 'Conta de origem' : 'Conta de destino' }}</label>
        <select class="ui-select" id="account_id" name="account_id" data-transaction-control data-account-select @disabled($typeValue === 'transfer')>
            <option value="">Selecione a conta</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" data-area-id="{{ $account->area_id ?? $account->church?->area_id }}" data-balance="{{ $accountBalances->get($account->id, '0.00') }}" @selected(old('account_id', $standardMovement?->financial_account_id) === $account->id)>
                    {{ $account->name }} · {{ $account->area?->name ?? $account->church?->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <div class="flex items-center justify-between gap-3"><label class="ui-label" for="category_id">Categoria</label><button class="mb-1.5 text-xs font-semibold text-brand-primary hover:text-brand-primary-hover" type="button" data-quick-category-trigger hidden>Adicionar categoria</button></div>
        <select class="ui-select" id="category_id" name="category_id" data-transaction-control data-category-select @disabled($typeValue === 'transfer')>
            <option value="">Selecione a categoria</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" data-area-id="{{ $category->area_id }}" data-category-type="{{ $category->type->value }}" @selected(old('category_id', $financialTransaction->category_id ?? '') === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="ui-label" for="department_id">Departamento</label>
        <select class="ui-select" id="department_id" name="department_id" data-transaction-control data-department-select @disabled($typeValue === 'transfer')>
            <option value="">Sem departamento</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" data-area-id="{{ $department->area_id }}" @selected(old('department_id', $financialTransaction->department_id ?? '') === $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <div class="flex items-center gap-1.5"><label class="ui-label" for="responsible_member_id">Responsável</label><x-tooltip id="responsible-member-tooltip" text="Membro que responde ou acompanha este lançamento. É opcional." /></div>
        <select class="ui-select" id="responsible_member_id" name="responsible_member_id" data-transaction-control data-responsible-select @disabled($typeValue === 'transfer')>
            <option value="">Sem responsável</option>
            @foreach ($responsibleMembers as $member)
                <option value="{{ $member->id }}" @selected(old('responsible_member_id', $financialTransaction->responsible_member_id ?? '') === $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <div class="flex items-center gap-1.5"><label class="ui-label" for="counterparty_name" data-counterparty-label>{{ $typeValue === 'expense' ? 'Favorecido ou fornecedor' : 'Contraparte' }}</label><x-tooltip id="counterparty-tooltip" text="Pessoa, empresa ou instituição do outro lado do pagamento ou recebimento." /></div>
        <input class="ui-input" id="counterparty_name" name="counterparty_name" value="{{ old('counterparty_name', $financialTransaction->counterparty_name ?? '') }}" maxlength="255" data-transaction-control @disabled($typeValue === 'transfer')>
    </div>

    <div data-document-field @if($typeValue !== 'expense') hidden @endif>
        <div class="flex items-center gap-1.5"><label class="ui-label" for="document_number">Número do documento</label><x-tooltip id="document-number-tooltip" text="Identifica o comprovante da saída, como nota fiscal, boleto, fatura, recibo ou comprovante bancário." /></div>
        <input class="ui-input" id="document_number" name="document_number" value="{{ old('document_number', $financialTransaction->document_number ?? '') }}" maxlength="255" data-transaction-control @disabled($typeValue !== 'expense')>
    </div>

    <div>
        <div class="flex items-center gap-1.5"><label class="ui-label">Competência</label><x-tooltip id="competence-tooltip" text="Mês e ano aos quais este lançamento se refere. Pode ser diferente da data em que ele foi pago ou recebido." /></div>
        <div class="grid grid-cols-[minmax(0,1fr)_7rem] gap-3">
            <select class="ui-select" id="competence_month_number" name="competence_month_number" data-transaction-control @disabled($typeValue === 'transfer')>
                <option value="">Mês</option>
                @foreach ($months as $number => $name)<option value="{{ $number }}" @selected((int) $competenceMonth === $number)>{{ $name }}</option>@endforeach
            </select>
            <select class="ui-select" id="competence_year" name="competence_year" data-transaction-control @disabled($typeValue === 'transfer')>
                <option value="">Ano</option>
                @for ($year = today()->year - 10; $year <= today()->year; $year++)<option value="{{ $year }}" @selected((int) $competenceYear === $year)>{{ $year }}</option>@endfor
            </select>
        </div>
    </div>
</section>

<section class="grid gap-5 rounded-2xl border border-border-default/80 bg-surface-card p-5 sm:grid-cols-2 sm:p-6" data-transfer-account-fields @if($typeValue !== 'transfer') hidden @endif>
    <div>
        <label class="ui-label" for="source_account_id">Conta de origem</label>
        <select class="ui-select" id="source_account_id" name="source_account_id" data-transfer-control @disabled($typeValue !== 'transfer')>
            <option value="">Selecione a origem</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected(old('source_account_id', $sourceMovement?->financial_account_id) === $account->id)>{{ $account->name }} · {{ $account->area?->name ?? $account->church?->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="ui-label" for="destination_account_id">Conta de destino</label>
        <select class="ui-select" id="destination_account_id" name="destination_account_id" data-transfer-control @disabled($typeValue !== 'transfer')>
            <option value="">Selecione o destino</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected(old('destination_account_id', $destinationMovement?->financial_account_id) === $account->id)>{{ $account->name }} · {{ $account->area?->name ?? $account->church?->name }}</option>
            @endforeach
        </select>
    </div>
</section>

<section class="grid gap-5 rounded-2xl border border-border-default/80 bg-surface-card p-5 sm:grid-cols-2 sm:p-6">
    <div class="sm:col-span-2">
        <label class="ui-label" for="title">Título</label>
        <input class="ui-input" id="title" name="title" value="{{ old('title', $financialTransaction->title ?? '') }}" maxlength="255" required autofocus>
    </div>
    <div>
        <label class="ui-label" for="amount">Valor (R$)</label>
        <input class="ui-input" id="amount" name="amount" type="text" inputmode="decimal" value="{{ old('amount', $financialTransaction->amount ?? '') }}" required data-transaction-amount data-currency-input>
        <p class="mt-2 hidden rounded-xl border border-warning/20 bg-warning-soft px-3 py-2 text-xs text-warning" data-negative-balance-warning role="status">Esta saída pode deixar a conta com saldo negativo. O sistema permitirá o lançamento e manterá este alerta.</p>
    </div>
    <div>
        <label class="ui-label" for="occurred_on">Data</label>
        <input class="ui-input" id="occurred_on" name="occurred_on" type="date" value="{{ old('occurred_on', isset($financialTransaction) ? $financialTransaction->occurred_on->toDateString() : today()->toDateString()) }}" required>
    </div>
    <div>
        <label class="ui-label" for="payment_method">Forma de pagamento</label>
        <select class="ui-select" id="payment_method" name="payment_method">
            <option value="">Selecione</option>
            @foreach ($paymentMethods as $method)
                <option value="{{ $method->value }}" @selected(old('payment_method', isset($financialTransaction) ? $financialTransaction->payment_method?->value : '') === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </select>
        <p class="mt-1.5 text-xs text-text-secondary">Obrigatória para pendências e lançamentos liquidados.</p>
    </div>
    @unless ($editing)
        <div>
            <label class="ui-label" for="status">Salvar como</label>
            <select class="ui-select" id="status" name="status" required data-transaction-status>
                <option value="draft" @selected(old('status', 'pending') === 'draft') data-draft-status>Rascunho — não afeta saldo</option>
                <option value="pending" @selected(old('status', 'pending') === 'pending')>Pendente — não afeta saldo</option>
                <option value="settled" @selected(old('status') === 'settled')>Liquidado — afeta saldo</option>
            </select>
        </div>
    @endunless
</section>

<div class="hidden rounded-2xl border border-warning/20 bg-warning-soft p-4 text-sm text-warning" role="status" data-negative-balance-warning>
    Esta saída liquidada deixará a conta com saldo negativo. A operação é permitida, mas revise os lançamentos históricos.
</div>

<section class="rounded-2xl border border-border-default/80 bg-surface-card p-5 sm:p-6">
    <label class="ui-label" for="description">Descrição</label>
    <textarea class="ui-input min-h-28" id="description" name="description" maxlength="4000">{{ old('description', $financialTransaction->description ?? '') }}</textarea>
</section>
