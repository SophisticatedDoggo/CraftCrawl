function setMobileActionsMenuOpen(isOpen) {
    const menus = document.querySelectorAll('[data-mobile-actions-menu]');
    const toggles = document.querySelectorAll('[data-mobile-actions-toggle]');
    const tabbars = new Set();

    menus.forEach((menu) => {
        menu.classList.toggle('is-open', isOpen);
    });

    toggles.forEach((toggle) => {
        toggle.setAttribute('aria-expanded', String(isOpen));

        const tabbar = toggle.closest('.mobile-app-tabbar');

        if (tabbar) {
            tabbars.add(tabbar);
        }

        if (!isOpen && typeof toggle.blur === 'function') {
            toggle.blur();
        }
    });

    tabbars.forEach((tabbar) => {
        const menuToggle = tabbar.querySelector('[data-mobile-actions-toggle]');
        const activeTab = tabbar.querySelector('.mobile-app-tab.is-active');

        setMobileTabThumb(tabbar, isOpen ? menuToggle : activeTab);
    });
}

window.CraftCrawlCloseMobileActionsMenu = function () {
    setMobileActionsMenuOpen(false);
};

function toggleMobileActionsMenu() {
    const menu = document.querySelector('.user-app-actions-menu[data-mobile-actions-menu]')
        || document.querySelector('[data-mobile-actions-menu]');
    const isOpen = menu ? !menu.classList.contains('is-open') : true;

    setMobileActionsMenuOpen(isOpen);
}

function setupMobileActionsMenu() {
    ensureViewportAnchoredMobileUi();

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

    if (document.documentElement.dataset.mobileActionsGlobalReady !== 'true') {
        document.documentElement.dataset.mobileActionsGlobalReady = 'true';
        document.addEventListener('click', (event) => {
            document.querySelectorAll('[data-mobile-actions-menu]').forEach((menu) => {
            const clickedToggle = event.target.closest('[data-mobile-actions-toggle]');

            if (!menu.contains(event.target) && !clickedToggle) {
                setMobileActionsMenuOpen(false);
            }
            });
        });
    }
}

function ensureViewportAnchoredMobileUi() {
    const persistentUi = document.querySelectorAll('.user-app-tabbar, .user-app-actions-menu');

    persistentUi.forEach((element) => {
        if (element.parentElement !== document.body) {
            document.body.appendChild(element);
        }
    });
}

function setupMomentumSafeMobileTabs() {
    if (document.documentElement.dataset.mobileTabsGlobalReady === 'true') {
        return;
    }
    document.documentElement.dataset.mobileTabsGlobalReady = 'true';
    let lastTouchActivatedAt = 0;
    let touchStartX = 0;
    let touchStartY = 0;

    function getMobileTab(target) {
        return target instanceof Element ? target.closest('.mobile-app-tab') : null;
    }

    function activateMobileTab(tab) {
        if (tab instanceof HTMLAnchorElement) {
            if (typeof window.CraftCrawlSwitchUserTab === 'function'
                && window.CraftCrawlSwitchUserTab(tab.href, { userInitiated: true })) {
                return;
            }

            if (typeof window.CraftCrawlNavigateUserShell === 'function'
                && window.CraftCrawlNavigateUserShell(tab.href, { userInitiated: true })) {
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

    if (tabbar) {
        tabbar.querySelectorAll('.mobile-app-tab.is-thumb-target').forEach((targetTab) => {
            targetTab.classList.remove('is-thumb-target');
        });
    }

    if (!tabbar || !tab) {
        if (tabbar) {
            tabbar.style.setProperty('--mobile-tab-thumb-opacity', '0');
            tabbar.classList.remove('has-mobile-tab-thumb');
            tabbar.classList.remove('is-mobile-tab-thumb-ready');
        }

        return;
    }

    const tabbarRect = tabbar.getBoundingClientRect();
    const tabRect = tab.getBoundingClientRect();

    if (!tabbarRect.width || !tabRect.width) {
        tabbar.style.setProperty('--mobile-tab-thumb-opacity', '0');
        tabbar.classList.remove('has-mobile-tab-thumb');
        tabbar.classList.remove('is-mobile-tab-thumb-ready');
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

    tab.classList.add('is-thumb-target');

    if (shouldAnimate) {
        tabbar.classList.add('is-mobile-tab-thumb-ready');
    }
}

function setupMobileTabThumbs() {
    ensureViewportAnchoredMobileUi();

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
        if (tabbar.dataset.thumbReady === 'true') {
            return;
        }
        tabbar.dataset.thumbReady = 'true';
    });

    syncThumbsWithoutAnimation();

    window.addEventListener('resize', syncThumbsWithoutAnimation);
    window.addEventListener('orientationchange', syncThumbsWithoutAnimation);
    window.addEventListener('craftcrawl:mobile-tab-state-settled', () => syncThumbs({ animate: true }));

    if (document.fonts && typeof document.fonts.ready?.then === 'function') {
        document.fonts.ready.then(syncThumbsWithoutAnimation).catch(() => {});
    }
}

window.CraftCrawlInitMobileActionsMenu = function () {
    setupMobileTabThumbs();
    setupMobileActionsMenu();
    setupMomentumSafeMobileTabs();
};
window.CraftCrawlInitMobileActionsMenu();
