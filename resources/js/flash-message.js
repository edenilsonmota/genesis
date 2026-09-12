document.querySelectorAll('[data-flash-dismiss]').forEach((button) => {
    button.addEventListener('click', () => {
        button.closest('[data-flash-message]')?.remove();
    });
});
