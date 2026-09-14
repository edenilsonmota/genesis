import Choices from 'choices.js';

const select = document.querySelector('[data-department-select]');
const trigger = document.querySelector('[data-quick-department-trigger]');
const modal = document.querySelector('[data-quick-department-modal]');

if (select) {
    const choices = new Choices(select, {
        allowHTML: false,
        itemSelectText: '',
        noResultsText: 'Nenhum departamento encontrado',
        searchEnabled: true,
        searchFloor: 1,
        searchPlaceholderValue: 'Buscar departamento',
        shouldSort: true,
    });

    if (!trigger || !modal) {
        return;
    }

    const form = modal.querySelector('[data-quick-department-form]');
    const nameInput = modal.querySelector('[data-quick-department-name]');
    const error = modal.querySelector('[data-quick-department-error]');
    const submit = modal.querySelector('[data-quick-department-submit]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    let searchTerm = '';
    let searchHasNoResults = false;

    const normalized = (value) => value
        .trim()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('pt-BR');
    const hasDepartment = (value) => [...select.options]
        .some((option) => normalized(option.text) === normalized(value));
    const syncTrigger = () => {
        const canCreate = searchTerm.length > 0 && searchHasNoResults && !hasDepartment(searchTerm);

        trigger.hidden = !canCreate;
        trigger.textContent = canCreate ? `Adicionar “${searchTerm}”` : 'Adicionar departamento';
    };
    const closeModal = () => {
        modal.close();
        error.hidden = true;
        error.textContent = '';
        trigger.focus();
    };

    select.addEventListener('search', (event) => {
        searchTerm = event.detail.value.trim().toLocaleUpperCase('pt-BR');
        searchHasNoResults = event.detail.resultCount === 0;

        const searchInput = select.parentElement?.querySelector('.choices__input--cloned');

        if (searchInput && searchInput.value !== searchTerm) {
            searchInput.value = searchTerm;
        }

        syncTrigger();
    });
    select.addEventListener('choice', () => {
        searchTerm = '';
        searchHasNoResults = false;
        syncTrigger();
    });
    trigger.addEventListener('click', () => {
        nameInput.value = searchTerm;
        error.hidden = true;
        modal.showModal();
        nameInput.focus();
    });
    modal.querySelectorAll('[data-quick-department-cancel]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        error.hidden = true;
        submit.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.errors?.name?.[0] ?? 'Não foi possível criar o departamento.');
            }

            const { department } = payload;
            choices.setChoices([{ value: department.id, label: department.name, selected: true }], 'value', 'label', false, true);
            searchTerm = '';
            searchHasNoResults = false;
            syncTrigger();
            closeModal();
        } catch (exception) {
            error.textContent = exception.message;
            error.hidden = false;
        } finally {
            submit.disabled = false;
        }
    });
}
