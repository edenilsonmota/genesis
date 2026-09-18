import IMask from 'imask';

const masks = new WeakMap();

const normalizeValue = (value) => {
    const sanitized = String(value || '').replace(/[^\d,.-]/g, '');
    if (sanitized.includes(',')) return sanitized.replace(/\./g, '').replace(',', '.');

    return sanitized;
};

export const currencyValue = (input) => Number(masks.get(input)?.typedValue || 0);

document.querySelectorAll('[data-currency-input]').forEach((input) => {
    const initial = normalizeValue(input.value);
    const mask = IMask(input, {
        mask: 'R$ num',
        lazy: false,
        blocks: {
            num: {
                mask: Number,
                scale: 2,
                signed: false,
                thousandsSeparator: '.',
                radix: ',',
                mapToRadix: ['.'],
                normalizeZeros: true,
                padFractionalZeros: true,
            },
        },
    });
    masks.set(input, mask);
    if (initial !== '') mask.typedValue = Number(initial);

    input.form?.addEventListener('submit', () => {
        input.value = String(mask.typedValue || '');
    });
});
