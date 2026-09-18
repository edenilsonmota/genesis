import Choices from 'choices.js';
import { currencyValue } from './currency-inputs';

const normalize = (value) => value.trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR');

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
    const quickTrigger = form.querySelector('[data-quick-category-trigger]');
    const modal = document.querySelector('[data-quick-category-modal]');
    const categoryCatalog = category
        ? [...category.options].filter((option) => option.value).map((option) => ({
            value: option.value,
            label: option.text,
            areaId: option.dataset.areaId,
            type: option.dataset.categoryType,
        }))
        : [];
    const categoryChoices = category ? new Choices(category, {
        allowHTML: false,
        itemSelectText: '',
        noResultsText: 'Nenhuma categoria encontrada',
        searchEnabled: true,
        searchFloor: 1,
        searchPlaceholderValue: 'Buscar categoria',
        shouldSort: true,
    }) : null;
    let searchTerm = '';
    let searchHasNoResults = false;
    let selectedType = form.dataset.fixedType
        || typeButtons.find((button) => button.getAttribute('aria-pressed') === 'true')?.dataset.transactionType
        || 'income';

    const categoryEntries = () => {
        const areaId = account?.selectedOptions[0]?.dataset.areaId;

        return [
            { value: '', label: 'Selecione a categoria', placeholder: true, selected: false },
            ...categoryCatalog
                .filter((entry) => entry.type === selectedType && (!areaId || entry.areaId === areaId))
                .map((entry) => ({ ...entry, selected: category?.value === entry.value })),
        ];
    };

    const refreshCategories = () => {
        if (!categoryChoices) return;
        const selected = category.value;
        categoryChoices.setChoices(categoryEntries(), 'value', 'label', true, true, true);
        if (categoryCatalog.some((entry) => entry.value === selected && categoryEntries().some((entry) => entry.value === selected))) {
            categoryChoices.setChoiceByValue(selected);
        }
    };

    const syncQuickCategoryTrigger = () => {
        const hasCategory = categoryCatalog.some((entry) => entry.type === selectedType && normalize(entry.label) === normalize(searchTerm));
        const canCreate = selectedType !== 'transfer' && searchTerm.length > 0 && searchHasNoResults && !hasCategory;

        if (quickTrigger) {
            quickTrigger.hidden = !canCreate;
            quickTrigger.textContent = canCreate ? `Adicionar “${searchTerm}”` : 'Adicionar categoria';
        }
    };

    const updateOptions = () => {
        const selectedAccount = account?.selectedOptions[0];
        const areaId = selectedAccount?.dataset.areaId;

        department?.querySelectorAll('option[data-area-id]').forEach((option) => {
            option.disabled = Boolean(areaId && option.dataset.areaId !== areaId);
        });
        if (department?.selectedOptions[0]?.disabled) department.value = '';
        refreshCategories();
        syncQuickCategoryTrigger();
    };

    const updateNegativeBalanceWarning = () => {
        if (!negativeBalanceWarning) return;
        const balance = Number(account?.selectedOptions[0]?.dataset.balance || 0);
        const value = amount ? currencyValue(amount) : 0;
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
            form.action = selectedType === 'expense' ? form.dataset.expenseAction : selectedType === 'transfer' ? form.dataset.transferAction : form.dataset.incomeAction;
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

    typeButtons.forEach((button) => button.addEventListener('click', () => { selectedType = button.dataset.transactionType; render(); }));
    account?.addEventListener('change', () => { updateOptions(); updateNegativeBalanceWarning(); });
    category?.addEventListener('search', (event) => { searchTerm = event.detail.value.trim(); searchHasNoResults = event.detail.resultCount === 0; syncQuickCategoryTrigger(); });
    category?.addEventListener('choice', () => { searchTerm = ''; searchHasNoResults = false; syncQuickCategoryTrigger(); });
    amount?.addEventListener('input', updateNegativeBalanceWarning);
    status?.addEventListener('change', render);

    if (quickTrigger && modal) {
        const quickForm = modal.querySelector('[data-quick-category-form]');
        const nameInput = modal.querySelector('[data-quick-category-name]');
        const typeInput = modal.querySelector('[data-quick-category-type]');
        const areaInput = modal.querySelector('[data-quick-category-area]');
        const error = modal.querySelector('[data-quick-category-error]');
        const submit = modal.querySelector('[data-quick-category-submit]');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const closeModal = () => { modal.close(); error.hidden = true; error.textContent = ''; quickTrigger.focus(); };

        quickTrigger.addEventListener('click', () => {
            nameInput.value = searchTerm;
            typeInput.value = selectedType;
            areaInput.value = account?.selectedOptions[0]?.dataset.areaId || '';
            error.hidden = true;
            modal.showModal();
            nameInput.focus();
        });
        modal.querySelectorAll('[data-quick-category-cancel]').forEach((button) => button.addEventListener('click', closeModal));
        quickForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            error.hidden = true;
            submit.disabled = true;
            try {
                const response = await fetch(quickForm.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(quickForm),
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.errors?.name?.[0] ?? 'Não foi possível criar a categoria.');
                const { category: created } = payload;
                if (!categoryCatalog.some((entry) => entry.value === created.id)) categoryCatalog.push({ value: created.id, label: created.name, areaId: created.area_id, type: created.type });
                refreshCategories();
                categoryChoices.setChoiceByValue(created.id);
                searchTerm = '';
                searchHasNoResults = false;
                syncQuickCategoryTrigger();
                closeModal();
            } catch (exception) {
                error.textContent = exception.message;
                error.hidden = false;
            } finally {
                submit.disabled = false;
            }
        });
    }

    render();
};

document.querySelectorAll('[data-financial-transaction-form]').forEach(initializeFinancialTransactionForm);
