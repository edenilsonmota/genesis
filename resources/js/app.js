import 'flowbite';
import './navigation';
import './flash-message';
import './organization';
import './members';
import './position-department';
import './uppercase-inputs';
import './financial-owner';
import './financial-transactions';
import './currency-inputs';
import './select-autosize';
import './date-inputs';
import './time-inputs';
import './password-visibility';

if (document.querySelector('[data-financial-overview]')) {
    import('./financial-overview');
}

if (document.querySelector('[data-dashboard-overview]')) {
    import('./dashboard-overview');
}

if (document.querySelector('[data-calendar]')) {
    import('./calendar');
}

if (document.querySelector('[data-calendar-event-form]')) {
    import('./calendar-event-form');
}
