const navigationToggle = document.querySelector('[data-navigation-toggle]');
const navigationPanel = document.querySelector('[data-navigation-panel]');

if (navigationToggle && navigationPanel) {
    navigationToggle.addEventListener('click', () => {
        const isExpanded = navigationToggle.getAttribute('aria-expanded') === 'true';

        navigationToggle.setAttribute('aria-expanded', String(!isExpanded));
        navigationPanel.classList.toggle('hidden', isExpanded);
    });
}
