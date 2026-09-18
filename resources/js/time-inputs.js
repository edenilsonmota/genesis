import IMask from 'imask';

document.querySelectorAll('[data-time-24]').forEach((input) => {
    IMask(input, {
        mask: 'HH:mm',
        lazy: false,
        blocks: {
            HH: { mask: IMask.MaskedRange, from: 0, to: 23, maxLength: 2, autofix: 'pad' },
            mm: { mask: IMask.MaskedRange, from: 0, to: 59, maxLength: 2, autofix: 'pad' },
        },
    });
});
