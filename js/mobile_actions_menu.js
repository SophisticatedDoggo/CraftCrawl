function setMobileActionsMenuOpen(isOpen) {
    const menus = document.querySelectorAll('[data-mobile-actions-menu]');
    const toggles = document.querySelectorAll('[data-mobile-actions-toggle]');

    menus.forEach((menu) => {
        menu.classList.toggle('is-open', isOpen);
    });

    toggles.forEach((toggle) => {
        toggle.setAttribute('aria-expanded', String(isOpen));

        const tabbar = toggle.closest('.mobile-app-tabbar');

        if (tabbar) {
            if (isOpen) {
                setMobileTabThumb(tabbar, toggle);
            } else {
                setMobileTabThumb(tabbar, tabbar.querySelector('.mobile-app-tab.is-active'));
            }
        }
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
        const tabbar = tab.closest('.mobile-app-tabbar');

        if (tabbar) {
            setMobileTabThumb(tabbar, tab);
        }

        if (tab instanceof HTMLAnchorElement) {
            if (typeof window.CraftCrawlSwitchUserTab === 'function'
                && window.CraftCrawlSwitchUserTab(tab.href)) {
                return;
            }

            if (typeof window.CraftCrawlNavigateWithLoader === 'function') {
                window.CraftCrawlNavigateWithLoader(tab.href);
            } else {
                window.location.href = tab.href;
            }
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

function setMobileTabThumb(tabbar, tab, options = {}) {
    const shouldAnimate = options.animate !== false;

    if (!tabbar || !tab) {
        if (tabbar) {
            tabbar.querySelectorAll('.mobile-app-tab.is-thumb-target').forEach((targetTab) => {
                targetTab.classList.remove('is-thumb-target');
            });
            tabbar.style.setProperty('--mobile-tab-thumb-opacity', '0');
            tabbar.classList.remove('has-mobile-tab-thumb');
            tabbar.classList.remove('is-mobile-tab-thumb-ready');
        }

        return;
    }

    const tabbarRect = tabbar.getBoundingClientRect();
    const tabRect = tab.getBoundingClientRect();

    if (!tabbarRect.width || !tabRect.width) {
        return;
    }

    if (!shouldAnimate) {
        tabbar.classList.remove('is-mobile-tab-thumb-ready');
    }

    tabbar.style.setProperty('--mobile-tab-thumb-left', `${tabRect.left - tabbarRect.left}px`);
    tabbar.style.setProperty('--mobile-tab-thumb-top', `${tabRect.top - tabbarRect.top}px`);
    tabbar.style.setProperty('--mobile-tab-thumb-width', `${tabRect.width}px`);
    tabbar.style.setProperty('--mobile-tab-thumb-height', `${tabRect.height}px`);
    tabbar.style.setProperty('--mobile-tab-thumb-opacity', '1');
    tabbar.classList.add('has-mobile-tab-thumb');

    tabbar.querySelectorAll('.mobile-app-tab.is-thumb-target').forEach((targetTab) => {
        targetTab.classList.remove('is-thumb-target');
    });
    tab.classList.add('is-thumb-target');

    if (shouldAnimate) {
        tabbar.classList.add('is-mobile-tab-thumb-ready');
    }
}

function setupMobileTabThumbs() {
    const tabbars = document.querySelectorAll('.mobile-app-tabbar');

    if (!tabbars.length) {
        return;
    }

    function syncThumbs(options = {}) {
        tabbars.forEach((tabbar) => {
            const openMenuToggle = tabbar.querySelector('[data-mobile-actions-toggle][aria-expanded="true"]');
            const activeTab = openMenuToggle || tabbar.querySelector('.mobile-app-tab.is-active');

            setMobileTabThumb(tabbar, activeTab, options);
        });
    }

    function enableThumbAnimationAfterLayout() {
        requestAnimationFrame(() => {
            tabbars.forEach((tabbar) => {
                if (tabbar.classList.contains('has-mobile-tab-thumb')) {
                    tabbar.classList.add('is-mobile-tab-thumb-ready');
                }
            });
        });
    }

    function syncThumbsWithoutAnimation() {
        syncThumbs({ animate: false });
        enableThumbAnimationAfterLayout();
    }

    tabbars.forEach((tabbar) => {
        tabbar.addEventListener('pointerdown', (event) => {
            const tab = event.target instanceof Element ? event.target.closest('.mobile-app-tab') : null;

            if (!tab || !tabbar.contains(tab)) {
                return;
            }

            setMobileTabThumb(tabbar, tab);
        });
    });

    syncThumbsWithoutAnimation();

    window.addEventListener('resize', syncThumbsWithoutAnimation);
    window.addEventListener('orientationchange', syncThumbsWithoutAnimation);

    if (document.fonts && typeof document.fonts.ready?.then === 'function') {
        document.fonts.ready.then(syncThumbsWithoutAnimation).catch(() => {});
    }
}

setupMobileTabThumbs();
setupMobileActionsMenu();
setupMomentumSafeMobileTabs();
