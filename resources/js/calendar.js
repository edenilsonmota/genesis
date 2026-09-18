import { Calendar } from 'fullcalendar';
import interactionPlugin from 'fullcalendar/interaction';
import dayGridPlugin from 'fullcalendar/daygrid';
import timeGridPlugin from 'fullcalendar/timegrid';
import listPlugin from 'fullcalendar/list';
import multiMonthPlugin from 'fullcalendar/multimonth';
import monarchPlugin from 'fullcalendar/themes/monarch';
import ptBrLocale from 'fullcalendar/locales/pt-br';
import 'fullcalendar/skeleton.css';
import 'fullcalendar/themes/monarch/theme.css';
import 'fullcalendar/themes/monarch/palettes/blue.css';

const calendarElement = document.querySelector('[data-calendar]');

if (calendarElement) {
    const filterForm = document.querySelector('[data-calendar-filters]');
    const viewSelect = document.querySelector('[data-calendar-view]');
    const feedback = document.querySelector('[data-calendar-feedback]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const setFeedback = (message, isError = false) => {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.hidden = !message;
        feedback.classList.toggle('ui-alert-error', isError);
        feedback.classList.toggle('border-success/20', !isError);
        feedback.classList.toggle('bg-success-soft', !isError);
        feedback.classList.toggle('text-success', !isError);
    };

    const requestEvents = async (info, successCallback, failureCallback) => {
        const url = new URL(calendarElement.dataset.feedUrl, window.location.origin);
        url.searchParams.set('start', info.startStr);
        url.searchParams.set('end', info.endStr);
        new FormData(filterForm).forEach((value, key) => {
            if (value) url.searchParams.set(key, value);
        });

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Não foi possível carregar os eventos.');
            successCallback(await response.json());
        } catch (error) {
            setFeedback(error.message, true);
            failureCallback(error);
        }
    };

    const updateSchedule = async (info) => {
        setFeedback('');
        try {
            const response = await fetch(info.event.extendedProps.scheduleUrl, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    start: info.event.startStr,
                    end: info.event.endStr,
                    all_day: info.event.allDay,
                }),
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(Object.values(payload.errors ?? {}).flat()[0] ?? 'Não foi possível alterar a data do evento.');
            setFeedback(payload.message ?? 'Evento atualizado.');
        } catch (error) {
            info.revert();
            setFeedback(error.message, true);
        }
    };

    const calendar = new Calendar(calendarElement, {
        plugins: [monarchPlugin, interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin, multiMonthPlugin],
        themeSystem: 'monarch',
        locale: ptBrLocale,
        initialView: window.matchMedia('(max-width: 639px)').matches ? 'listUpcoming' : (viewSelect?.value || 'dayGridMonth'),
        views: {
            listUpcoming: {
                type: 'list',
                duration: { days: 90 },
            },
        },
        headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
        buttonText: { today: 'Hoje' },
        firstDay: 0,
        nowIndicator: true,
        navLinks: true,
        selectable: calendarElement.dataset.canCreate === 'true',
        selectMirror: true,
        editable: false,
        eventResizableFromStart: true,
        dayMaxEvents: true,
        noEventsText: 'Nenhum evento neste período',
        multiMonthMaxColumns: 4,
        singleMonthMinWidth: 235,
        slotMinTime: '06:00:00',
        slotMaxTime: '24:00:00',
        slotDuration: '00:30:00',
        scrollTime: '18:00:00',
        height: 'auto',
        events: requestEvents,
        eventDrop: updateSchedule,
        eventResize: updateSchedule,
        eventClick: (info) => {
            if (info.event.url) {
                info.jsEvent.preventDefault();
                window.location.assign(info.event.url);
            }
        },
        eventDidMount: (info) => {
            const details = [
                info.event.extendedProps.type,
                info.event.extendedProps.scope,
                info.event.extendedProps.location,
                info.event.extendedProps.status,
            ].filter(Boolean).join(' · ');
            info.el.title = details;
            if (info.event.extendedProps.status === 'Cancelado') info.el.classList.add('calendar-event-cancelled');
        },
        select: (info) => {
            const url = new URL(calendarElement.dataset.createUrl, window.location.origin);
            const allDay = info.allDay;
            const start = info.start;
            const end = new Date(info.end);
            if (allDay) end.setDate(end.getDate() - 1);
            const localDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                return `${year}-${month}-${day}T${hours}:${minutes}`;
            };
            url.searchParams.set('start', localDate(start));
            url.searchParams.set('end', localDate(end));
            url.searchParams.set('all_day', allDay ? '1' : '0');
            window.location.assign(url);
        },
        datesSet: (info) => {
            if (viewSelect && viewSelect.value !== info.view.type) viewSelect.value = info.view.type;
        },
        loading: (loading) => calendarElement.toggleAttribute('aria-busy', loading),
    });

    calendar.render();
    filterForm?.addEventListener('submit', (event) => { event.preventDefault(); setFeedback(''); calendar.refetchEvents(); });
    filterForm?.querySelector('[data-calendar-filter-clear]')?.addEventListener('click', () => {
        filterForm.reset();
        setFeedback('');
        calendar.refetchEvents();
    });
    viewSelect?.addEventListener('change', () => calendar.changeView(viewSelect.value));
}
