document.querySelectorAll('[data-dependent-cities]').forEach((container) => {
    const stateSelect = container.querySelector('[data-state-select]');
    const citySelect = container.querySelector('[data-city-select]');
    const citiesBaseUrl = container.dataset.citiesBaseUrl;

    if (!stateSelect || !citySelect || !citiesBaseUrl) {
        return;
    }

    const loadCities = async (stateId, selectedCityId = '') => {
        const emptyLabel = 'Selecione';

        citySelect.replaceChildren(new Option(stateId ? 'Carregando…' : 'Escolha um estado primeiro', ''));
        citySelect.disabled = true;

        if (!stateId) {
            return false;
        }

        try {
            const response = await fetch(`${citiesBaseUrl}/${encodeURIComponent(stateId)}/cities`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Não foi possível carregar as cidades.');
            }

            const payload = await response.json();
            const options = [new Option(emptyLabel, '')];

            payload.data.forEach((city) => {
                options.push(new Option(city.name, city.id));
            });

            citySelect.replaceChildren(...options);
            citySelect.disabled = false;

            if (selectedCityId) {
                citySelect.value = String(selectedCityId);

                if (citySelect.value !== String(selectedCityId)) {
                    throw new Error('A cidade não está disponível no estado informado.');
                }
            }

            return true;
        } catch {
            citySelect.replaceChildren(new Option('Erro ao carregar cidades', ''));

            return false;
        }
    };

    stateSelect.addEventListener('change', () => {
        loadCities(stateSelect.value);
    });

    const postalCodeInput = container.querySelector('[data-postal-code]');
    const postalCodeFeedback = container.querySelector('[data-postal-code-feedback]');
    const postalCodeLookupUrl = container.dataset.postalCodeLookupUrl;

    if (!postalCodeInput || !postalCodeFeedback || !postalCodeLookupUrl) {
        return;
    }

    const streetInput = container.querySelector('[data-street-input]');
    const neighborhoodInput = container.querySelector('[data-neighborhood-input]');
    const complementInput = container.querySelector('[data-complement-input]');
    let activeLookup;
    let lastSuccessfulPostalCode = '';

    const showPostalCodeFeedback = (message, type = 'neutral') => {
        const colors = {
            error: 'text-danger',
            neutral: 'text-text-secondary',
            success: 'text-success',
        };

        postalCodeFeedback.textContent = message;
        postalCodeFeedback.className = `mt-2 text-sm ${colors[type]}`;
        postalCodeFeedback.classList.toggle('hidden', !message);
    };

    const lookupPostalCode = async () => {
        const postalCode = postalCodeInput.value.replace(/\D/g, '').slice(0, 8);
        postalCodeInput.value = postalCode.length > 5
            ? `${postalCode.slice(0, 5)}-${postalCode.slice(5)}`
            : postalCode;

        if (postalCode.length !== 8) {
            activeLookup?.abort();
            lastSuccessfulPostalCode = '';
            showPostalCodeFeedback('');

            return;
        }

        if (postalCode === lastSuccessfulPostalCode) {
            return;
        }

        activeLookup?.abort();
        activeLookup = new AbortController();
        const currentLookup = activeLookup;
        showPostalCodeFeedback('Consultando CEP…');

        try {
            const response = await fetch(`${postalCodeLookupUrl}/${postalCode}`, {
                headers: { Accept: 'application/json' },
                signal: currentLookup.signal,
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message || 'Não foi possível consultar o CEP.');
            }

            stateSelect.value = String(payload.data.state.id);

            if (!await loadCities(payload.data.state.id, payload.data.city.id)) {
                throw new Error('Não foi possível selecionar a cidade deste CEP.');
            }

            if (payload.data.street && streetInput) {
                streetInput.value = payload.data.street;
            }

            if (payload.data.neighborhood && neighborhoodInput) {
                neighborhoodInput.value = payload.data.neighborhood;
            }

            if (payload.data.complement && complementInput && !complementInput.value) {
                complementInput.value = payload.data.complement;
            }

            lastSuccessfulPostalCode = postalCode;
            showPostalCodeFeedback('Endereço localizado.', 'success');
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            lastSuccessfulPostalCode = '';
            showPostalCodeFeedback(error.message, 'error');
        } finally {
            if (activeLookup === currentLookup) {
                activeLookup = undefined;
            }
        }
    };

    postalCodeInput.addEventListener('input', lookupPostalCode);
    postalCodeInput.addEventListener('blur', lookupPostalCode);
});

document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});
