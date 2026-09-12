<header class="grid gap-5">
    <div>
        <p class="ui-page-kicker">Financeiro</p>
        <h1 class="mt-1 ui-page-title">Contas e categorias</h1>
        <p class="mt-2 ui-page-copy">Cadastros que preparam a estrutura para as futuras movimentações financeiras.</p>
    </div>

    <nav class="flex flex-wrap gap-2 border-b border-border-default" aria-label="Seções de contas e categorias">
        @if ($canViewFinancialAccounts)
            <a @class([
                'border-b-2 px-4 py-3 text-sm font-semibold transition',
                'border-brand-primary text-brand-primary' => $activeTab === 'accounts',
                'border-transparent text-text-secondary hover:border-brand-sky hover:text-text-primary' => $activeTab !== 'accounts',
            ]) href="{{ route('finance.accounts.index') }}" @if($activeTab === 'accounts') aria-current="page" @endif>
                Contas financeiras
            </a>
        @endif
        @if ($canViewFinancialCategories)
            <a @class([
                'border-b-2 px-4 py-3 text-sm font-semibold transition',
                'border-brand-primary text-brand-primary' => $activeTab === 'categories',
                'border-transparent text-text-secondary hover:border-brand-sky hover:text-text-primary' => $activeTab !== 'categories',
            ]) href="{{ route('finance.categories.index') }}" @if($activeTab === 'categories') aria-current="page" @endif>
                Categorias
            </a>
        @endif
    </nav>
</header>
