const digits = (value, limit) => value.replace(/\D/g, '').slice(0, limit);

document.querySelectorAll('[data-cpf-mask]').forEach((input) => {
    const format = () => {
        const value = digits(input.value, 11);
        input.value = value
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1-$2');
    };

    input.addEventListener('input', format);
    format();
});

document.querySelectorAll('[data-phone-mask]').forEach((input) => {
    const format = () => {
        const value = digits(input.value, 11);
        input.value = value.length > 10
            ? value.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3')
            : value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
    };

    input.addEventListener('input', format);
    format();
});

document.querySelectorAll('[data-member-steps]').forEach((form) => {
    const panels = [...form.querySelectorAll('[data-step-panel]')];
    const indicators = [...form.querySelectorAll('[data-step-indicator]')];
    const nextButton = form.querySelector('[data-step-next]');
    const previousButton = form.querySelector('[data-step-previous]');
    let currentStep = Number(form.dataset.initialStep || 1);

    const showStep = (step) => {
        currentStep = step;
        panels.forEach((panel) => panel.classList.toggle('hidden', Number(panel.dataset.stepPanel) !== step));
        indicators.forEach((indicator) => {
            const active = Number(indicator.dataset.stepIndicator) === step;
            indicator.classList.toggle('bg-brand-primary', active);
            indicator.classList.toggle('text-white', active);
            indicator.classList.toggle('bg-surface-muted', !active);
            indicator.classList.toggle('text-text-secondary', !active);
        });
    };

    nextButton?.addEventListener('click', () => {
        const requiredFields = [...panels[0].querySelectorAll('[required]')];
        const invalidField = requiredFields.find((field) => !field.reportValidity());

        if (!invalidField) {
            showStep(2);
            panels[1].querySelector('select, input')?.focus();
        }
    });
    previousButton?.addEventListener('click', () => showStep(1));
    form.addEventListener('submit', () => {
        form.querySelectorAll('[data-cpf-mask], [data-phone-mask], [data-postal-code]').forEach((input) => {
            input.value = input.value.replace(/\D/g, '');
        });
    });
    showStep(currentStep);
});
