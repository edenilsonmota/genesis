import { Drawer } from 'flowbite';

const shell = document.querySelector('[data-sidebar-shell]');

if (shell) {
    const desktopSidebar = document.querySelector('[data-desktop-sidebar]');
    const mobileSidebar = document.querySelector('[data-mobile-sidebar]');
    const mobileOpenButton = document.querySelector('[data-mobile-sidebar-open]');
    const mobileCloseButton = document.querySelector('[data-mobile-sidebar-close]');
    const desktopMediaQuery = window.matchMedia('(min-width: 64rem)');
    let mobileDrawer;
    let lastFocusedElement;
    let pointerInsideDesktopSidebar = false;

    const getStoredBoolean = (key, fallback) => {
        try {
            const value = window.localStorage.getItem(key);

            return value === null ? fallback : value === 'true';
        } catch {
            return fallback;
        }
    };

    const saveBoolean = (key, value) => {
        try {
            window.localStorage.setItem(key, String(value));
        } catch {
            // A navegação continua funcional quando o navegador bloqueia o armazenamento local.
        }
    };

    const setDesktopSidebar = (expanded) => {
        shell.dataset.sidebarExpanded = String(expanded);
        desktopSidebar?.setAttribute('data-expanded', String(expanded));
    };

    const setCategory = (category, isOpen, persist = true) => {
        document.querySelectorAll(`[data-sidebar-category-panel="${category}"]`).forEach((panel) => {
            panel.classList.toggle('hidden', !isOpen);
        });
        document.querySelectorAll(`[data-sidebar-category-toggle][data-sidebar-category="${category}"]`).forEach((toggle) => {
            toggle.setAttribute('aria-expanded', String(isOpen));
        });

        if (persist) {
            saveBoolean(`genesis.sidebar.${category}.open`, isOpen);
        }
    };

    const isCategoryActive = (category) => document.querySelector(
        `[data-sidebar-category-panel="${category}"][data-sidebar-category-active="true"]`,
    ) !== null;

    const focusableInMobileSidebar = () => Array.from(mobileSidebar?.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ) ?? []).filter((element) => element.offsetParent !== null && !element.hasAttribute('hidden'));

    const syncDesktopSidebar = () => {
        setDesktopSidebar(pointerInsideDesktopSidebar || desktopSidebar?.contains(document.activeElement));
    };

    setDesktopSidebar(false);
    [...new Set([...document.querySelectorAll('[data-sidebar-category-toggle]')]
        .map((toggle) => toggle.dataset.sidebarCategory)
        .filter(Boolean))]
        .forEach((category) => {
            setCategory(
                category,
                isCategoryActive(category) || getStoredBoolean(`genesis.sidebar.${category}.open`, true),
                false,
            );
        });
    requestAnimationFrame(() => {
        shell.dataset.sidebarReady = 'true';
    });

    desktopSidebar?.addEventListener('mouseenter', () => {
        pointerInsideDesktopSidebar = true;
        setDesktopSidebar(true);
    });
    desktopSidebar?.addEventListener('mouseleave', () => {
        pointerInsideDesktopSidebar = false;
        syncDesktopSidebar();
    });
    desktopSidebar?.addEventListener('focusin', () => setDesktopSidebar(true));
    desktopSidebar?.addEventListener('focusout', () => requestAnimationFrame(syncDesktopSidebar));

    document.querySelectorAll('[data-sidebar-category-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const category = toggle.dataset.sidebarCategory;

            if (!category) {
                return;
            }

            setCategory(category, toggle.getAttribute('aria-expanded') !== 'true');
        });
    });

    if (mobileSidebar && mobileOpenButton) {
        mobileDrawer = new Drawer(mobileSidebar, {
            placement: 'left',
            backdrop: true,
            bodyScrolling: false,
            backdropClasses: 'fixed inset-0 z-40 bg-text-primary/45 backdrop-blur-sm',
            onShow: () => {
                mobileOpenButton.setAttribute('aria-expanded', 'true');
                mobileCloseButton?.focus();
            },
            onHide: () => {
                mobileOpenButton.setAttribute('aria-expanded', 'false');
                lastFocusedElement?.focus?.();
            },
        });

        mobileOpenButton.addEventListener('click', () => {
            lastFocusedElement = document.activeElement;
            mobileDrawer.show();
        });
        mobileCloseButton?.addEventListener('click', () => mobileDrawer.hide());
        mobileSidebar.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', () => mobileDrawer.hide());
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab' || !mobileDrawer.isVisible()) {
                return;
            }

            const focusableElements = focusableInMobileSidebar();
            const firstElement = focusableElements.at(0);
            const lastElement = focusableElements.at(-1);

            if (!firstElement || !lastElement) {
                return;
            }

            if (event.shiftKey && document.activeElement === firstElement) {
                event.preventDefault();
                lastElement.focus();
            } else if (!event.shiftKey && document.activeElement === lastElement) {
                event.preventDefault();
                firstElement.focus();
            }
        });

        desktopMediaQuery.addEventListener('change', (event) => {
            if (event.matches && mobileDrawer.isVisible()) {
                mobileDrawer.hide();
            }
        });
    }
}
