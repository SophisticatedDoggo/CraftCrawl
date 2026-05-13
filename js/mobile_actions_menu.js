function setMobileActionsMenuOpen(isOpen) {
    const menus = document.querySelectorAll('[data-mobile-actions-menu]');
    const toggles = document.querySelectorAll('[data-mobile-actions-toggle]');

    menus.forEach((menu) => {
        menu.classList.toggle('is-open', isOpen);
    });

    toggles.forEach((toggle) => {
        toggle.setAttribute('aria-expanded', String(isOpen));
    });
}

function toggleMobileActionsMenu() {
    const menu = document.querySelector('[data-mobile-actions-menu]');
    const isOpen = menu ? !menu.classList.contains('is-open') : true;

    setMobileActionsMenuOpen(isOpen);
}

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
                toggleMobileActionsMenu();
            });
        });
    });

    document.addEventListener('click', (event) => {
        menus.forEach((menu) => {
            const clickedToggle = event.target.closest('[data-mobile-actions-toggle]');

            if (!menu.contains(event.target) && !clickedToggle) {
                setMobileActionsMenuOpen(false);
            }
        });
    });
}

function setupMomentumSafeMobileTabs() {
    let lastTouchActivatedAt = 0;
    let touchStartX = 0;
    let touchStartY = 0;

    function getMobileTab(target) {
        return target instanceof Element ? target.closest('.mobile-app-tab') : null;
    }

    function activateMobileTab(tab) {
        if (tab instanceof HTMLAnchorElement) {
            window.location.href = tab.href;
            return;
        }

        if (tab.matches('[data-mobile-actions-toggle]')) {
            toggleMobileActionsMenu();
        }
    }

    document.addEventListener('touchstart', (event) => {
        const tab = getMobileTab(event.target);

        if (!tab || !tab.closest('.mobile-app-tabbar') || !event.touches.length) {
            return;
        }

        touchStartX = event.touches[0].clientX;
        touchStartY = event.touches[0].clientY;
    }, { passive: true });

    document.addEventListener('touchend', (event) => {
        const tab = getMobileTab(event.target);

        if (!tab || !tab.closest('.mobile-app-tabbar') || !event.changedTouches.length) {
            return;
        }

        const deltaX = Math.abs(event.changedTouches[0].clientX - touchStartX);
        const deltaY = Math.abs(event.changedTouches[0].clientY - touchStartY);

        if (deltaX > 10 || deltaY > 10) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        lastTouchActivatedAt = Date.now();
        activateMobileTab(tab);
    }, { passive: false });

    document.addEventListener('click', (event) => {
        const tab = getMobileTab(event.target);

        if (!tab || !tab.closest('.mobile-app-tabbar') || Date.now() - lastTouchActivatedAt > 700) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
    }, true);
}

setupMobileActionsMenu();
setupMomentumSafeMobileTabs();
