window.CraftCrawlInitUserTabShell = function (root = document) {
    const shell = root.querySelector('[data-user-tab-shell]');
    const tabbar = document.querySelector('.mobile-app-tabbar');

    if (!shell || !tabbar || shell.dataset.userTabShellReady === 'true') {
        return false;
    }
    shell.dataset.userTabShellReady = 'true';

    const routeByTab = { map: 'portal.php', events: 'events.php', feed: 'feed.php' };
    const titleByTab = { map: 'CraftCrawl | Home', events: 'CraftCrawl | Events', feed: 'CraftCrawl | Feed' };
    const tabFromPath = (pathname) => {
        if (pathname.endsWith('/portal.php') || pathname.endsWith('/user/')) return 'map';
        if (pathname.endsWith('/events.php')) return 'events';
        if (pathname.endsWith('/feed.php')) return 'feed';
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
        shell.closest('[data-user-page-content]')?.querySelectorAll('[data-user-tab-map-only]').forEach((element) => {
            element.hidden = tab !== 'map';
        });
        window.CraftCrawlCloseMobileActionsMenu?.();
        document.title = titleByTab[tab];
        if (options.updateHistory) history.pushState({ craftcrawlUserTab: tab }, '', `${routeByTab[tab]}${options.hash || ''}`);
        window.dispatchEvent(new CustomEvent('craftcrawl:user-tab-changed', { detail: { tab } }));
    }

    window.CraftCrawlSwitchUserTab = function (destination) {
        if (!document.contains(shell) || shell.closest('[data-user-page-content]')?.hidden) return false;
        const url = new URL(destination, window.location.href);
        const tab = tabFromPath(url.pathname);
        if (!routeByTab[tab]) return false;
        syncTab(tab, { updateHistory: tab !== shell.dataset.activeUserTab || url.hash !== window.location.hash, hash: url.hash });
        if (tab === 'map' && url.hash === '#checkin-panel') {
            document.getElementById('checkin-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
            if (window.CraftCrawlSwitchUserTab?.(link.href)) event.preventDefault();
        });
    }

    const initialTab = tabFromPath(window.location.pathname);
    if (initialTab) syncTab(initialTab);
    return true;
};
window.CraftCrawlInitUserTabShell();
