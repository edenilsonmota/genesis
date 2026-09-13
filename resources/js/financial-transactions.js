const initializeFinancialTransactionForm = (form) => {
    const typeButtons = [...form.querySelectorAll('[data-transaction-type]')];
    const standardFields = form.querySelector('[data-standard-account-fields]');
    const transferFields = form.querySelector('[data-transfer-account-fields]');
    const standardControls = [...form.querySelectorAll('[data-transaction-control]')];
    const transferControls = [...form.querySelectorAll('[data-transfer-control]')];
    const account = form.querySelector('[data-account-select]');
    const category = form.querySelector('[data-category-select]');
    const department = form.querySelector('[data-department-select]');
    const accountLabel = form.querySelector('[data-account-label]');
    const counterpartyLabel = form.querySelector('[data-counterparty-label]');
    const documentField = form.querySelector('[data-document-field]');
    const documentInput = form.querySelector('#document_number');
    const status = form.querySelector('[data-transaction-status]');
    const draftStatus = form.querySelector('[data-draft-status]');
    const paymentMethod = form.querySelector('#payment_method');
    const amount = form.querySelector('[data-transaction-amount]');
    const negativeBalanceWarning = form.querySelector('[data-negative-balance-warning]');
    let selectedType = form.dataset.fixedType
        || typeButtons.find((button) => button.getAttribute('aria-pressed') === 'true')?.dataset.transactionType
        || 'income';

    const updateOptions = () => {
        const selectedAccount = account?.selectedOptions[0];
        const areaId = selectedAccount?.dataset.areaId;

        category?.querySelectorAll('option[data-category-type]').forEach((option) => {
            option.disabled = option.dataset.categoryType !== selectedType || (areaId && option.dataset.areaId !== areaId);
        });
        department?.querySelectorAll('option[data-area-id]').forEach((option) => {
            option.disabled = Boolean(areaId && option.dataset.areaId !== areaId);
        });

        if (category?.selectedOptions[0]?.disabled) category.value = '';
        if (department?.selectedOptions[0]?.disabled) department.value = '';
    };

    const updateNegativeBalanceWarning = () => {
        if (!negativeBalanceWarning) return;
        const balance = Number(account?.selectedOptions[0]?.dataset.balance || 0);
        const value = Number(amount?.value || 0);
        negativeBalanceWarning.classList.toggle('hidden', selectedType !== 'expense' || value <= balance);
    };

    const render = () => {
        const isTransfer = selectedType === 'transfer';
        standardFields?.toggleAttribute('hidden', isTransfer);
        transferFields?.toggleAttribute('hidden', !isTransfer);
        standardControls.forEach((control) => { control.disabled = isTransfer; });
        transferControls.forEach((control) => { control.disabled = !isTransfer; });

        typeButtons.forEach((button) => {
            const active = button.dataset.transactionType === selectedType;
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            button.classList.toggle('border-brand-primary', active);
            button.classList.toggle('bg-brand-primary-soft', active);
            button.classList.toggle('text-brand-primary', active);
            button.classList.toggle('border-border-default', !active);
            button.classList.toggle('text-text-secondary', !active);
        });

        if (!form.dataset.fixedType) {
            form.action = selectedType === 'expense'
                ? form.dataset.expenseAction
                : selectedType === 'transfer'
                    ? form.dataset.transferAction
                    : form.dataset.incomeAction;
            const url = new URL(window.location.href);
            url.searchParams.set('type', selectedType);
            window.history.replaceState({}, '', url);
        }

        if (accountLabel) accountLabel.textContent = selectedType === 'expense' ? 'Conta de origem' : 'Conta de destino';
        if (counterpartyLabel) counterpartyLabel.textContent = selectedType === 'expense' ? 'Favorecido ou fornecedor' : 'Contraparte';
        documentField?.toggleAttribute('hidden', selectedType !== 'expense');
        if (documentInput) documentInput.disabled = selectedType !== 'expense';

        if (draftStatus) draftStatus.disabled = isTransfer;
        if (isTransfer && status?.value === 'draft') status.value = 'pending';
        if (paymentMethod) paymentMethod.required = isTransfer || status?.value !== 'draft';

        updateOptions();
        updateNegativeBalanceWarning();
    };

    typeButtons.forEach((button) => button.addEventListener('click', () => {
        selectedType = button.dataset.transactionType;
        render();
    }));
    account?.addEventListener('change', () => { updateOptions(); updateNegativeBalanceWarning(); });
    amount?.addEventListener('input', updateNegativeBalanceWarning);
    status?.addEventListener('change', render);
    render();
};

document.querySelectorAll('[data-financial-transaction-form]').forEach(initializeFinancialTransactionForm);
