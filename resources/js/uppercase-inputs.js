const uppercase = (input) => {
    const start = input.selectionStart;
    const end = input.selectionEnd;
    const value = input.value.toLocaleUpperCase('pt-BR');

    if (input.value === value) {
        return;
    }

    input.value = value;
    input.setSelectionRange(start, end);
};

document.querySelectorAll('[data-uppercase-input]').forEach((input) => {
    input.addEventListener('input', (event) => {
        if (!event.isComposing) {
            uppercase(input);
        }
    });
    input.addEventListener('compositionend', () => uppercase(input));
});
