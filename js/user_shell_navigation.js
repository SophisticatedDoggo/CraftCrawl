(function () {
    const baseFiles = new Set(['portal.php', 'events.php', 'feed.php']);
    const shellFiles = new Set([...baseFiles, 'friends.php', 'profile.php', 'settings.php', 'feed_post.php', 'business_details.php']);
    let navigating = false;

    function absoluteUrl(value, base = window.location.href) { return new URL(value, base).href; }
    function currentFile(url = window.location.href) { return new URL(url, window.location.href).pathname.split('/').pop() || 'portal.php'; }
    function isBaseUrl(url) { return baseFiles.has(currentFile(url)); }
    function isShellUrl(url) {
        const next = new URL(url, window.location.href);
        return next.origin === window.location.origin
            && shellFiles.has(currentFile(next.href))
            && (next.pathname.includes('/user/') || currentFile(next.href) === 'business_details.php');
    }
    function activeContent() { return document.querySelector('[data-user-page-content]:not([hidden])'); }
    function baseContent() { return document.querySelector('[data-user-page-content] [data-user-tab-shell]')?.closest('[data-user-page-content]') || null; }

    function setActiveTab(url) {
        const file = currentFile(url);
        const active = file === 'portal.php' ? 'map' : file === 'events.php' ? 'events' : file === 'feed.php' ? 'feed' : '';
        document.querySelectorAll('.mobile-app-tabbar .mobile-app-tab').forEach((tab) => {
            if (!(tab instanceof HTMLAnchorElement)) return;
            const fileName = currentFile(tab.href);
            const tabName = fileName === 'portal.php' ? 'map' : fileName === 'events.php' ? 'events' : fileName === 'feed.php' ? 'feed' : '';
            tab.classList.toggle('is-active', Boolean(active) && tabName === active);
        });
        window.CraftCrawlCloseMobileActionsMenu?.();
        window.CraftCrawlInitMobileActionsMenu?.();
    }

    function copyInlineScript(script) {
        const clone = document.createElement('script');
        clone.textContent = script.textContent;
        document.body.appendChild(clone);
        clone.remove();
    }

    function loadExternalScript(script, baseUrl) {
        const src = absoluteUrl(script.getAttribute('src'), baseUrl);
        if ([...document.scripts].some((existing) => existing.src === src)) return Promise.resolve();
        return new Promise((resolve, reject) => {
            const clone = document.createElement('script');
            clone.src = src;
            clone.onload = resolve;
            clone.onerror = reject;
            document.body.appendChild(clone);
        });
    }

    async function hydrateAssets(doc, baseUrl) {
        doc.querySelectorAll('link[rel="stylesheet"][href]').forEach((link) => {
            const href = absoluteUrl(link.getAttribute('href'), baseUrl);
            if ([...document.querySelectorAll('link[rel="stylesheet"][href]')].some((existing) => existing.href === href)) return;
            const clone = document.createElement('link');
            clone.rel = 'stylesheet';
            clone.href = href;
            document.head.appendChild(clone);
        });
        for (const script of doc.querySelectorAll('script')) {
            if (script.src) await loadExternalScript(script, baseUrl);
            else if (script.textContent.trim()) copyInlineScript(script);
        }
    }

    function initSwappedContent(root) {
        window.CraftCrawlInitUserTabShell?.(root);
        window.CraftCrawlInitFriends?.(root);
        window.CraftCrawlInitPaletteSwitcher?.(root);
        window.CraftCrawlInitAppIconSwitcher?.(root);
        window.CraftCrawlInitProfilePhotoCrop?.(root);
        window.CraftCrawlInitBadgeShowcase?.(root);
        window.CraftCrawlInitFeedThread?.(root);
        window.CraftCrawlInitMobileActionsMenu?.();
    }

    async function fetchDocument(url) {
        const response = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'CraftCrawlShell' } });
        if (!response.ok) throw new Error('Navigation failed.');
        return new DOMParser().parseFromString(await response.text(), 'text/html');
    }

    async function navigate(url, options = {}) {
        if (navigating || !isShellUrl(url)) return false;
        navigating = true;
        document.documentElement.classList.add('user-shell-is-navigating');
        try {
            const destinationIsBase = isBaseUrl(url);
            const liveBaseContent = baseContent();
            const visibleContent = activeContent();

            if (destinationIsBase && liveBaseContent) {
                if (visibleContent && visibleContent !== liveBaseContent) visibleContent.remove();
                liveBaseContent.hidden = false;
                document.body.className = 'portal-body';
                window.CraftCrawlSwitchUserTab?.(url);
            } else {
                const doc = await fetchDocument(url);
                const nextContent = doc.querySelector('[data-user-page-content]');
                if (!nextContent || !visibleContent) throw new Error('Missing shell content.');

                if (!destinationIsBase && liveBaseContent && visibleContent === liveBaseContent) {
                    liveBaseContent.hidden = true;
                    liveBaseContent.after(nextContent);
                } else {
                    visibleContent.replaceWith(nextContent);
                }

                document.body.className = doc.body.className;
                document.title = doc.title;
                await hydrateAssets(doc, url);
                initSwappedContent(nextContent);
            }

            if (!options.replace) history.pushState({ craftcrawlUserShell: true }, '', url);
            setActiveTab(url);
            window.scrollTo(0, 0);
            document.dispatchEvent(new CustomEvent('craftcrawl:user-shell-navigated', { detail: { url } }));
            return true;
        } catch (_) {
            window.location.href = url;
            return true;
        } finally {
            navigating = false;
            document.documentElement.classList.remove('user-shell-is-navigating');
        }
    }

    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link || link.target || link.hasAttribute('download') || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (!isShellUrl(link.href)) return;

        event.preventDefault();
        event.stopPropagation();

        if (typeof window.CraftCrawlSwitchUserTab === 'function' && window.CraftCrawlSwitchUserTab(link.href)) return;
        navigate(link.href);
    }, true);
    window.addEventListener('popstate', () => { if (isShellUrl(window.location.href)) navigate(window.location.href, { replace: true }); });
    window.CraftCrawlNavigateUserShell = navigate;
    setActiveTab(window.location.href);
})();
