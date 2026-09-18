import Choices from 'choices.js';

document.querySelectorAll('[data-calendar-event-form]').forEach((form) => {
    const allDay = form.querySelector('[data-event-all-day]');
    const timeFields = [...form.querySelectorAll('[data-event-time]')];
    const scopeType = form.querySelector('[data-event-scope-type]');
    const churchField = form.querySelector('[data-event-church-field]');
    const churchSelect = form.querySelector('[data-event-church]');
    const visibility = form.querySelector('[data-event-visibility]');
    const department = form.querySelector('[data-event-department]');
    const responsible = form.querySelector('[data-event-responsible]');
    const eventType = form.querySelector('[data-event-type]');
    const quickTypeTrigger = form.querySelector('[data-quick-event-type-trigger]');
    const typeModal = document.querySelector('[data-quick-event-type-modal]');
    const memberCatalog = responsible
        ? [...responsible.options].filter((option) => option.value).map((option) => ({
            value: option.value,
            label: option.text,
            churchIds: (option.dataset.churchIds || '').split(',').filter(Boolean),
            selected: option.selected,
        }))
        : [];
    const choicesOptions = {
        allowHTML: false,
        itemSelectText: '',
        searchEnabled: true,
        searchFloor: 1,
        shouldSort: true,
    };
    const eventTypeChoices = eventType ? new Choices(eventType, {
        ...choicesOptions,
        noResultsText: 'Nenhum tipo encontrado',
        searchPlaceholderValue: 'Buscar tipo',
    }) : null;
    const responsibleChoices = responsible ? new Choices(responsible, {
        ...choicesOptions,
        noResultsText: 'Nenhum membro encontrado',
        searchPlaceholderValue: 'Buscar responsável',
    }) : null;
    let eventTypeSearchTerm = '';

    const renderMembers = () => {
        if (!responsibleChoices) return;
        const selected = responsible.value;
        const isChurch = (scopeType?.value || 'church') === 'church';
        const churchId = churchSelect?.value;
        const choices = memberCatalog
            .filter((member) => !isChurch || !churchId || member.churchIds.includes(churchId))
            .map((member) => ({ ...member, selected: member.value === selected }));
        responsibleChoices.setChoices([
            { value: '', label: 'Sem responsável', placeholder: true },
            ...choices,
        ], 'value', 'label', true, true, true);
        if (choices.some((choice) => choice.value === selected)) responsibleChoices.setChoiceByValue(selected);
    };

    const render = () => {
        const isAllDay = allDay?.checked ?? false;
        timeFields.forEach((field) => {
            field.toggleAttribute('hidden', isAllDay);
            field.querySelector('input')?.toggleAttribute('required', !isAllDay);
        });

        const isChurch = (scopeType?.value || 'church') === 'church';
        churchField?.toggleAttribute('hidden', !isChurch);
        if (churchSelect) churchSelect.required = isChurch;
        if (department) department.required = visibility?.value === 'department';
        renderMembers();
    };

    allDay?.addEventListener('change', render);
    scopeType?.addEventListener('change', render);
    churchSelect?.addEventListener('change', render);
    visibility?.addEventListener('change', render);
    eventType?.addEventListener('search', (event) => { eventTypeSearchTerm = event.detail.value.trim(); });

    if (quickTypeTrigger && typeModal && eventTypeChoices) {
        const quickForm = typeModal.querySelector('[data-quick-event-type-form]');
        const nameInput = typeModal.querySelector('[data-quick-event-type-name]');
        const error = typeModal.querySelector('[data-quick-event-type-error]');
        const submit = typeModal.querySelector('[data-quick-event-type-submit]');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const closeModal = () => {
            typeModal.close();
            error.hidden = true;
            error.textContent = '';
            quickTypeTrigger.focus();
        };

        quickTypeTrigger.addEventListener('click', () => {
            nameInput.value = eventTypeSearchTerm;
            error.hidden = true;
            error.textContent = '';
            typeModal.showModal();
            nameInput.focus();
        });
        typeModal.querySelectorAll('[data-quick-event-type-cancel]').forEach((button) => button.addEventListener('click', closeModal));
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
                if (!response.ok) throw new Error(payload.errors?.name?.[0] ?? 'Não foi possível criar o tipo de evento.');
                const created = payload.type;
                if (!eventType.querySelector(`option[value="${created.id}"]`)) {
                    eventTypeChoices.setChoices([{ value: created.id, label: created.name }], 'value', 'label', false);
                }
                eventTypeChoices.setChoiceByValue(created.id);
                eventTypeSearchTerm = '';
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
});
