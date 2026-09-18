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

if (document.querySelector('[data-financial-overview]')) {
    import('./financial-overview');
}
