function setupMobileActionsMenu() {
    const menus = document.querySelectorAll('[data-mobile-actions-menu]');

    menus.forEach((menu) => {
        const toggles = document.querySelectorAll('[data-mobile-actions-toggle]');

        if (!toggles.length) {
            return;
        }

        toggles.forEach((toggle) => {
            if (toggle.dataset.ready === 'true') {
                return;
            }

            toggle.dataset.ready = 'true';

            toggle.addEventListener('click', (event) => {
                event.stopPropagation();
                const isOpen = menu.classList.toggle('is-open');
                toggles.forEach((item) => item.setAttribute('aria-expanded', String(isOpen)));
            });
        });
    });

    document.addEventListener('click', (event) => {
        menus.forEach((menu) => {
            const clickedToggle = event.target.closest('[data-mobile-actions-toggle]');

            if (!menu.contains(event.target) && !clickedToggle) {
                menu.classList.remove('is-open');
                document.querySelectorAll('[data-mobile-actions-toggle]').forEach((toggle) => {
                    toggle.setAttribute('aria-expanded', 'false');
                });
            }
        });
    });
}

setupMobileActionsMenu();
