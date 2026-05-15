(function () {
    const shell = document.querySelector('[data-user-tab-shell]');
    const tabbar = document.querySelector('.mobile-app-tabbar');

    if (!shell || !tabbar) {
        return;
    }

    const routeByTab = {
        map: 'portal.php',
        events: 'events.php',
        feed: 'feed.php'
    };
    const titleByTab = {
        map: 'CraftCrawl | Home',
        events: 'CraftCrawl | Events',
        feed: 'CraftCrawl | Feed'
    };

    function tabFromPath(pathname) {
        if (pathname.endsWith('/events.php')) {
            return 'events';
        }

        if (pathname.endsWith('/feed.php')) {
            return 'feed';
        }

        return 'map';
    }

    function syncTab(tab, options = {}) {
        if (!routeByTab[tab]) {
            return;
        }

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
        document.querySelectorAll('[data-user-tab-map-only]').forEach((element) => {
            element.hidden = tab !== 'map';
        });

        document.title = titleByTab[tab];

        if (options.updateHistory) {
            history.pushState({ craftcrawlUserTab: tab }, '', `${routeByTab[tab]}${options.hash || ''}`);
        }

        window.dispatchEvent(new CustomEvent('craftcrawl:user-tab-changed', { detail: { tab } }));
    }

    window.CraftCrawlSwitchUserTab = function (destination) {
        const url = new URL(destination, window.location.href);
        const tab = tabFromPath(url.pathname);

        if (!routeByTab[tab]) {
            return false;
        }

        syncTab(tab, {
            updateHistory: tab !== shell.dataset.activeUserTab || url.hash !== window.location.hash,
            hash: url.hash
        });

        if (tab === 'map' && url.hash === '#checkin-panel') {
            document.getElementById('checkin-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            window.scrollTo({ top: 0, behavior: 'instant' });
        }

        return true;
    };

    tabbar.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a.mobile-app-tab[href]') : null;
        if (!link || !tabbar.contains(link)) {
            return;
        }

        const tab = tabFromPath(new URL(link.href, window.location.href).pathname);
        if (!routeByTab[tab]) {
            return;
        }

        event.preventDefault();
        window.CraftCrawlSwitchUserTab(link.href);
    });

    window.addEventListener('popstate', () => {
        syncTab(tabFromPath(window.location.pathname));
    });
}());
