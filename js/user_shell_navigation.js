(function () {
    const baseFiles = new Set(['portal.php', 'events.php', 'feed.php', 'quests.php']);
    const shellFiles = new Set([...baseFiles, 'friends.php', 'rewards.php', 'profile.php', 'settings.php', 'feed_post.php', 'business_details.php']);
    let navigating = false;
    const baseScrollPositions = new Map();
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }

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
    function saveBaseScroll(url = window.location.href) {
        if (isBaseUrl(url)) {
            baseScrollPositions.set(currentFile(url), window.scrollY || document.documentElement.scrollTop || 0);
        }
    }
    function restoreBaseScroll(url) {
        const savedScroll = baseScrollPositions.get(currentFile(url));
        if (typeof savedScroll !== 'number') return false;
        window.requestAnimationFrame(() => window.scrollTo(0, savedScroll));
        return true;
    }

    function forceCloseFeedThreadOverlay() {
        if (typeof window.CraftCrawlDestroyFeedThreadOverlay === 'function') {
            window.CraftCrawlDestroyFeedThreadOverlay({ skipReturnAnchor: true });
        } else {
            document.querySelectorAll('[data-feed-thread-overlay]').forEach((overlay) => {
                overlay.hidden = true;
                overlay.remove();
            });
            if (window.CraftCrawlFeedThreadOverlayState) {
                window.CraftCrawlFeedThreadOverlayState.overlay = null;
                window.CraftCrawlFeedThreadOverlayState.content = null;
                window.CraftCrawlFeedThreadOverlayState.itemKey = '';
                window.CraftCrawlFeedThreadOverlayState.baseUrl = '';
                window.CraftCrawlFeedThreadOverlayState.closeTimer = 0;
            }
        }
        document.body.classList.remove('feed-thread-overlay-open', 'feed-comment-composer-open');
        document.documentElement.classList.remove('feed-thread-open-requested');
        document.documentElement.style.removeProperty('--feed-compose-keyboard-offset');
    }

    function cleanOrphanedFeedThreadOverlay(url = window.location.href) {
        if (currentFile(url) !== 'feed_post.php') {
            forceCloseFeedThreadOverlay();
        }
    }

    function setActiveTab(url) {
        const nextUrl = new URL(url, window.location.href);
        const file = currentFile(nextUrl.href);
        const threadItem = file === 'feed_post.php' ? nextUrl.searchParams.get('item') || '' : '';
        const active = file === 'portal.php'
            ? 'map'
            : file === 'events.php' || threadItem.startsWith('event:')
                ? 'events'
                : file === 'feed.php' || file === 'feed_post.php'
                    ? 'feed'
                    : file === 'quests.php'
                        ? 'quests'
                        : '';
        document.querySelectorAll('.mobile-app-tabbar .mobile-app-tab').forEach((tab) => {
            if (!(tab instanceof HTMLAnchorElement)) return;
            const fileName = currentFile(tab.href);
            const tabName = fileName === 'portal.php' ? 'map' : fileName === 'events.php' ? 'events' : fileName === 'feed.php' ? 'feed' : fileName === 'quests.php' ? 'quests' : '';
            tab.classList.toggle('is-active', Boolean(active) && tabName === active);
        });
        window.CraftCrawlCloseMobileActionsMenu?.();
        window.CraftCrawlInitMobileActionsMenu?.();
        window.dispatchEvent(new CustomEvent('craftcrawl:mobile-tab-state-settled'));
    }

    function copyInlineScript(script) {
        if (script.matches('[data-craftcrawl-google-analytics]')) return;
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
        window.CraftCrawlInitPortalEvents?.(root);
        window.CraftCrawlInitBusinessDetailsMap?.(root);
        window.CraftCrawlInitBusinessGallery?.(root);
        window.CraftCrawlInitBusinessPosts?.(root);
        window.CraftCrawlInitCheckIn?.(root);
        window.CraftCrawlInitReviewPhotos?.(root);
        window.CraftCrawlInitReviewEditToggle?.(root);
        window.CraftCrawlInitPaletteSwitcher?.(root);
        window.CraftCrawlInitAppIconSwitcher?.(root);
        window.CraftCrawlInitProfilePhotoCrop?.(root);
        window.CraftCrawlInitBadgeShowcase?.(root);
        window.CraftCrawlInitProfileListSearch?.(root);
        window.CraftCrawlInitProfileGrid?.(root);
        window.CraftCrawlInitFeedThread?.(root);
        window.CraftCrawlInitSocialClubDisclaimer?.(root);
        window.CraftCrawlInitPullToRefresh?.(root);
        window.CraftCrawlInitMobileActionsMenu?.();
    }

    async function fetchDocument(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: options.noStore ? 'no-store' : 'default',
            headers: { 'X-Requested-With': 'CraftCrawlShell' }
        });
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
            const returningToBase = destinationIsBase && liveBaseContent && visibleContent !== liveBaseContent;
            const isFeedThreadOpen = document.documentElement.classList.contains('feed-thread-open-requested');

            if (destinationIsBase && liveBaseContent) {
                document.querySelectorAll('[data-user-page-content]').forEach((content) => {
                    if (content !== liveBaseContent) content.remove();
                });
                liveBaseContent.hidden = false;
                document.body.className = 'portal-body';
                window.CraftCrawlSwitchUserTab?.(url, {
                    userInitiated: Boolean(options.userInitiated),
                    trackPageView: false,
                    skipSaveScroll: returningToBase,
                    preserveScroll: true
                });
                restoreBaseScroll(url);
            } else {
                if (!destinationIsBase && liveBaseContent && visibleContent === liveBaseContent) {
                    saveBaseScroll();
                }
                const doc = await fetchDocument(url, { noStore: Boolean(options.noStore) });
                const nextContent = doc.querySelector('[data-user-page-content]');
                if (!nextContent || !visibleContent) throw new Error('Missing shell content.');

                if (!destinationIsBase && liveBaseContent && visibleContent === liveBaseContent) {
                    document.querySelectorAll('[data-user-page-content]').forEach((content) => {
                        if (content !== liveBaseContent) content.remove();
                    });
                    liveBaseContent.hidden = true;
                    liveBaseContent.after(nextContent);
                } else {
                    visibleContent.replaceWith(nextContent);
                }

                document.body.className = doc.body.className;
                document.title = doc.title;
                await hydrateAssets(doc, url);
                if (isFeedThreadOpen) {
                    const threadPage = nextContent.querySelector('.feed-thread-page');
                    threadPage?.classList.add('feed-thread-page-entering');
                    window.setTimeout(() => threadPage?.classList.remove('feed-thread-page-entering'), 420);
                }
                initSwappedContent(nextContent);
            }

            if (options.replace) history.replaceState({ craftcrawlUserShell: true }, '', url);
            else history.pushState({ craftcrawlUserShell: true }, '', url);
            cleanOrphanedFeedThreadOverlay(url);
            setActiveTab(url);
            if (!destinationIsBase || !restoreBaseScroll(url)) window.scrollTo(0, 0);
            document.dispatchEvent(new CustomEvent('craftcrawl:user-shell-navigated', { detail: { url } }));
            window.CraftCrawlTrackPageView?.(url, document.title);
            return true;
        } catch (_) {
            window.location.href = url;
            return true;
        } finally {
            document.documentElement.classList.remove('feed-thread-open-requested');
            navigating = false;
            document.documentElement.classList.remove('user-shell-is-navigating');
        }
    }

    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link || link.target || link.hasAttribute('download') || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        // Shell pages are swapped into the DOM, so their back links do not always
        // receive the one-time listener registered by theme_init.js on page load.
        // Preserve the shared back-link contract here before normal shell routing
        // treats the fallback href as a fresh navigation.
        const feedThreadPage = link.closest('.feed-thread-page');
        if (link.hasAttribute('data-back-link') && feedThreadPage) {
            const overlay = link.closest('[data-feed-thread-overlay]');
            const itemKey = feedThreadPage.dataset.feedThreadItemKey || '';

            if (overlay && typeof window.CraftCrawlCloseFeedThreadOverlay === 'function') {
                event.preventDefault();
                event.stopPropagation();
                window.CraftCrawlCloseFeedThreadOverlay({
                    useHistory: true,
                    returnItemKey: itemKey
                });
                return;
            }

            if (itemKey.startsWith('event:')) {
                event.preventDefault();
                event.stopPropagation();
                const fallbackUrl = link.href;
                if (typeof window.CraftCrawlSwitchUserTab === 'function'
                    && window.CraftCrawlSwitchUserTab(fallbackUrl, { userInitiated: true })) {
                    history.replaceState({ craftcrawlUserShell: true }, '', fallbackUrl);
                    return;
                }
                navigate(fallbackUrl, { userInitiated: true, replace: true });
                return;
            }
        }

        if (link.hasAttribute('data-back-link') && window.history.length > 1) {
            if (link.closest('[data-event-detail-overlay]') && typeof window.CraftCrawlDismissEventDetailOverlay === 'function') {
                event.preventDefault();
                event.stopPropagation();
                window.CraftCrawlDismissEventDetailOverlay();
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            const fallbackUrl = link.href;
            const startingUrl = window.location.href;
            window.history.back();
            window.setTimeout(() => {
                if (window.location.href === startingUrl) {
                    window.location.href = fallbackUrl;
                }
            }, 350);
            return;
        }

        const linkUrl = new URL(link.href, window.location.href);
        const targetUrl = linkUrl.href;
        const isFeedThreadOverlayLink = Boolean(link.closest('[data-feed-thread-overlay]'));
        if (isFeedThreadOverlayLink && isShellUrl(targetUrl)) {
            event.preventDefault();
            event.stopPropagation();
            const shouldReplaceOverlayState = Boolean(history.state?.craftcrawlFeedThreadOverlay)
                || currentFile(window.location.href) === 'feed_post.php';
            window.CraftCrawlCloseFeedThreadOverlay?.({ useHistory: false, immediate: true, skipReturnAnchor: true });
            forceCloseFeedThreadOverlay();
            if (typeof window.CraftCrawlSwitchUserTab === 'function'
                && window.CraftCrawlSwitchUserTab(targetUrl, { userInitiated: true })) {
                if (shouldReplaceOverlayState) history.replaceState({ craftcrawlUserShell: true }, '', targetUrl);
                forceCloseFeedThreadOverlay();
                return;
            }
            navigate(targetUrl, {
                userInitiated: true,
                replace: shouldReplaceOverlayState
            }).finally(forceCloseFeedThreadOverlay);
            return;
        }

        if (!isShellUrl(link.href)) return;

        event.preventDefault();
        event.stopPropagation();

        if (typeof window.CraftCrawlSwitchUserTab === 'function'
            && window.CraftCrawlSwitchUserTab(link.href, { userInitiated: true })) return;
        navigate(link.href, { userInitiated: true });
    }, true);
    window.addEventListener('popstate', () => {
        cleanOrphanedFeedThreadOverlay();
        if (isShellUrl(window.location.href)) navigate(window.location.href, { replace: true });
    });
    window.addEventListener('pageshow', () => cleanOrphanedFeedThreadOverlay());
    window.CraftCrawlNavigateUserShell = navigate;
    window.CraftCrawlSaveUserShellBaseScroll = saveBaseScroll;
    window.CraftCrawlRefreshUserShell = function () {
        return navigate(window.location.href, { replace: true, noStore: true });
    };
    setActiveTab(window.location.href);
})();
