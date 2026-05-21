window.CraftCrawlInitUserTabShell = function (root = document) {
    const shell = root.querySelector('[data-user-tab-shell]');
    const tabbar = document.querySelector('.mobile-app-tabbar');

    if (!shell || !tabbar || shell.dataset.userTabShellReady === 'true') {
        return false;
    }
    shell.dataset.userTabShellReady = 'true';

    const routeByTab = { map: 'portal.php', events: 'events.php', feed: 'feed.php', quests: 'quests.php' };
    const titleByTab = { map: 'CraftCrawl | Home', events: 'CraftCrawl | Events', feed: 'CraftCrawl | Feed', quests: 'CraftCrawl | Quests' };
    const tabScrollPositions = {};
    const tabFromPath = (pathname) => {
        if (pathname.endsWith('/portal.php') || pathname.endsWith('/user/')) return 'map';
        if (pathname.endsWith('/events.php')) return 'events';
        if (pathname.endsWith('/feed.php')) return 'feed';
        if (pathname.endsWith('/quests.php')) return 'quests';
        return null;
    };

    function syncTab(tab, options = {}) {
        if (!document.contains(shell) || !routeByTab[tab]) return;
        shell.dataset.activeUserTab = tab;
        shell.querySelectorAll('[data-user-tab-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.userTabPanel !== tab;
        });
        tabbar.querySelectorAll('.mobile-app-tab[href]').forEach((link) => {
            const nextTab = tabFromPath(new URL(link.href, window.location.href).pathname);
            link.classList.toggle('is-active', nextTab === tab && !(tab === 'map' && link.hash));
        });
        document.body.classList.toggle('portal-body-compact', tab !== 'map');
        document.body.classList.toggle('feed-page-body', tab === 'feed');
        document.body.classList.toggle('events-page-body', tab === 'events');
        shell.closest('[data-user-page-content]')?.querySelectorAll('[data-user-tab-map-only]').forEach((element) => {
            element.hidden = tab !== 'map';
        });
        window.CraftCrawlCloseMobileActionsMenu?.();
        document.title = titleByTab[tab];
        if (options.updateHistory) history.pushState({ craftcrawlUserTab: tab }, '', `${routeByTab[tab]}${options.search || ''}${options.hash || ''}`);
        if (options.updateHistory && options.trackPageView !== false) {
            window.CraftCrawlTrackPageView?.(window.location.href, document.title);
        }
        window.dispatchEvent(new CustomEvent('craftcrawl:user-tab-changed', {
            detail: {
                tab,
                url: options.url || `${routeByTab[tab]}${options.search || ''}${options.hash || ''}`,
                userInitiated: Boolean(options.userInitiated)
            }
        }));
    }

    window.CraftCrawlSwitchUserTab = function (destination, options = {}) {
        if (!document.contains(shell) || shell.closest('[data-user-page-content]')?.hidden) return false;
        const url = new URL(destination, window.location.href);
        const tab = tabFromPath(url.pathname);
        if (!routeByTab[tab]) return false;
        const previousTab = shell.dataset.activeUserTab;
        if (!options.skipSaveScroll && previousTab && routeByTab[previousTab]) {
            tabScrollPositions[previousTab] = window.scrollY || document.documentElement.scrollTop || 0;
            window.CraftCrawlSaveUserShellBaseScroll?.();
        }
        syncTab(tab, {
            updateHistory: tab !== shell.dataset.activeUserTab || url.search !== window.location.search || url.hash !== window.location.hash,
            search: url.search,
            hash: url.hash,
            url: url.href,
            userInitiated: Boolean(options.userInitiated)
        });
        if (options.preserveScroll) {
            return true;
        }
        if (tab === 'map' && url.hash === '#checkin-panel') {
            document.getElementById('checkin-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else if (previousTab !== tab && typeof tabScrollPositions[tab] === 'number') {
            window.requestAnimationFrame(() => window.scrollTo(0, tabScrollPositions[tab]));
        } else {
            window.scrollTo(0, 0);
        }
        return true;
    };

    if (tabbar.dataset.userTabClickReady !== 'true') {
        tabbar.dataset.userTabClickReady = 'true';
        tabbar.addEventListener('click', (event) => {
            const link = event.target instanceof Element ? event.target.closest('a.mobile-app-tab[href]') : null;
            if (!link || !tabbar.contains(link)) return;
            const tab = tabFromPath(new URL(link.href, window.location.href).pathname);
            if (!routeByTab[tab]) return;
            if (window.CraftCrawlSwitchUserTab?.(link.href, { userInitiated: true })) event.preventDefault();
        });
    }

    const initialTab = tabFromPath(window.location.pathname);
    if (initialTab) {
        syncTab(initialTab, {
            search: window.location.search,
            hash: window.location.hash,
            url: window.location.href
        });
    }
    return true;
};
window.CraftCrawlInitUserTabShell();
