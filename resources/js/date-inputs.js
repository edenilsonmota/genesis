import flatpickr from 'flatpickr';
import { Portuguese } from 'flatpickr/dist/l10n/pt.js';
import 'flatpickr/dist/flatpickr.min.css';

document.querySelectorAll('input[type="date"]').forEach((input) => {
    flatpickr(input, {
        locale: Portuguese,
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        allowInput: true,
        disableMobile: true,
        minDate: input.min || undefined,
        maxDate: input.max || undefined,
    });
});
