document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.dataset.passwordTarget);
    if (!input) return;

    button.addEventListener('click', () => {
        const willShow = input.type === 'password';
        input.type = willShow ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(willShow));
        button.setAttribute('aria-label', willShow ? 'Ocultar senha' : 'Mostrar senha');
        button.querySelector('[data-password-show-icon]')?.classList.toggle('hidden', willShow);
        button.querySelector('[data-password-hide-icon]')?.classList.toggle('hidden', !willShow);
        input.focus({ preventScroll: true });
    });
});
