const initializeFinancialOwnerFields = () => {
    document.querySelectorAll('[data-financial-owner-fields]').forEach((container) => {
        const ownerType = container.querySelector('[data-financial-owner-type]');
        const areaField = container.querySelector('[data-financial-area-field]');
        const churchField = container.querySelector('[data-financial-church-field]');
        const areaSelect = areaField?.querySelector('select');
        const churchSelect = churchField?.querySelector('select');

        if (!ownerType || !areaField || !churchField || !areaSelect || !churchSelect) {
            return;
        }

        const synchronize = () => {
            const areaSelected = ownerType.value === 'area';

            areaField.hidden = !areaSelected;
            areaSelect.disabled = !areaSelected;
            areaSelect.required = areaSelected;
            churchField.hidden = areaSelected;
            churchSelect.disabled = areaSelected;
            churchSelect.required = !areaSelected;
        };

        ownerType.addEventListener('change', synchronize);
        synchronize();
    });
};

initializeFinancialOwnerFields();
