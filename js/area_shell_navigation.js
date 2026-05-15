(function () {
    const config = window.CraftCrawlAreaShellConfig;
    if (!config || !Array.isArray(config.routes)) return;
    const routes = new Set(config.routes);
    let navigating = false;
    const fileOf = (url = window.location.href) => new URL(url, window.location.href).pathname.split('/').pop() || config.home;
    const isShellUrl = (url) => {
        const next = new URL(url, window.location.href);
        return next.origin === window.location.origin && next.pathname.includes(`/${config.area}/`) && routes.has(fileOf(next.href));
    };
    const activeContent = () => document.querySelector('[data-area-page-content]');
    function routeKey(file) { return config.active[file] || ''; }
    function syncNav(url) {
        window.CraftCrawlCloseMobileActionsMenu?.();
        const active = routeKey(fileOf(url));
        document.querySelectorAll('.mobile-app-tabbar .mobile-app-tab').forEach((tab) => {
            if (!(tab instanceof HTMLAnchorElement)) return;
            tab.classList.toggle('is-active', Boolean(active) && routeKey(fileOf(tab.href)) === active);
        });
        window.CraftCrawlInitMobileActionsMenu?.();
    }
    function init(root) {
        window.CraftCrawlInitPaletteSwitcher?.(root);
        window.CraftCrawlInitBusinessEvents?.(root);
        window.CraftCrawlInitBusinessAnalytics?.(root);
        window.CraftCrawlInitBusinessReviewResponses?.(root);
        window.CraftCrawlInitBusinessHoursEditor?.(root);
        window.CraftCrawlInitBusinessPosts?.(root);
        window.CraftCrawlInitBusinessAddressAutofill?.(root);
        window.CraftCrawlInitAdminReviewEditToggle?.(root);
        window.CraftCrawlInitMobileActionsMenu?.();
    }
    async function loadScript(script) {
        if (!script.src) return;
        const src = new URL(script.src, window.location.href).href;
        if ([...document.scripts].some((item) => item.src === src)) return;
        await new Promise((resolve, reject) => {
            const clone = document.createElement('script'); clone.src = src; if (script.id) clone.id = script.id; clone.onload = resolve; clone.onerror = reject; document.body.appendChild(clone);
        });
    }
    async function hydrate(doc) {
        doc.querySelectorAll('link[rel="stylesheet"][href]').forEach((link) => {
            const href = new URL(link.href, window.location.href).href;
            if ([...document.querySelectorAll('link[rel="stylesheet"][href]')].some((item) => item.href === href)) return;
            const clone = document.createElement('link'); clone.rel = 'stylesheet'; clone.href = href; document.head.appendChild(clone);
        });
        for (const script of doc.querySelectorAll('script[src]')) await loadScript(script);
    }
    async function navigate(url, options = {}) {
        if (navigating || !isShellUrl(url)) return false;
        navigating = true;
        try {
            const response = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'CraftCrawlShell' } });
            if (!response.ok) throw new Error();
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = doc.querySelector('[data-area-page-content]');
            const current = activeContent();
            if (!next || !current) throw new Error();
            current.replaceWith(next);
            document.body.className = doc.body.className;
            document.title = doc.title;
            await hydrate(doc);
            init(next);
            if (!options.replace) history.pushState({ craftcrawlAreaShell: config.area }, '', url);
            syncNav(url);
            window.scrollTo(0, 0);
            return true;
        } catch (_) {
            window.location.href = url;
            return true;
        } finally { navigating = false; }
    }
    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link || link.target || link.hasAttribute('download') || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || !isShellUrl(link.href)) return;
        event.preventDefault(); event.stopPropagation(); navigate(link.href);
    }, true);
    window.addEventListener('popstate', () => { if (isShellUrl(window.location.href)) navigate(window.location.href, { replace: true }); });
    syncNav(window.location.href);
})();
