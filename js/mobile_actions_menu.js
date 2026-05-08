function setupMobileActionsMenu() {
    const menus = document.querySelectorAll('[data-mobile-actions-menu]');

    menus.forEach((menu) => {
        const toggle = menu.querySelector('[data-mobile-actions-toggle]');

        if (!toggle || toggle.dataset.ready === 'true') {
            return;
        }

        toggle.dataset.ready = 'true';

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = menu.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', String(isOpen));
        });
    });

    document.addEventListener('click', (event) => {
        menus.forEach((menu) => {
            const toggle = menu.querySelector('[data-mobile-actions-toggle]');

            if (!menu.contains(event.target)) {
                menu.classList.remove('is-open');
                toggle?.setAttribute('aria-expanded', 'false');
            }
        });
    });
}

setupMobileActionsMenu();
