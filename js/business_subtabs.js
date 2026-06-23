var craftcrawlSubtabNavSelector = [
    '.business-subtab-nav',
    '.profile-subtab-nav',
    '.quest-subtab-nav',
    '.friends-subtab-nav',
    '.analytics-mode-tabs'
].join(', ');

var craftcrawlObservedSubtabNavs = new WeakSet();
var craftcrawlSubtabResizeObserver = typeof ResizeObserver === 'function'
    ? new ResizeObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.contentRect.width || !entry.contentRect.height) return;
            entry.target.classList.remove('is-subtab-thumb-ready');
            craftcrawlUpdateSubtabThumb(entry.target, false);
        });
    })
    : null;

function craftcrawlUpdateSubtabThumb(nav, animate) {
    if (!nav) return false;
    var active = nav.querySelector('.is-active');
    if (!active) {
        nav.style.setProperty('--subtab-thumb-opacity', '0');
        return false;
    }

    var navRect = nav.getBoundingClientRect();
    var btnRect = active.getBoundingClientRect();
    if (!navRect.width || !btnRect.width) return false;

    if (animate && !nav.classList.contains('is-subtab-thumb-ready')) {
        nav.classList.add('is-subtab-thumb-ready');
    }

    nav.style.setProperty('--subtab-thumb-left', (btnRect.left - navRect.left) + 'px');
    nav.style.setProperty('--subtab-thumb-top', (btnRect.top - navRect.top) + 'px');
    nav.style.setProperty('--subtab-thumb-width', btnRect.width + 'px');
    nav.style.setProperty('--subtab-thumb-height', btnRect.height + 'px');
    nav.style.setProperty('--subtab-thumb-opacity', '1');
    return true;
}

window.CraftCrawlUpdateSubtabThumb = craftcrawlUpdateSubtabThumb;

function craftcrawlFindSubtabNavs(root) {
    root = root || document;
    var navs = [];

    if (root.matches && root.matches(craftcrawlSubtabNavSelector)) {
        navs.push(root);
    }
    if (root.querySelectorAll) {
        root.querySelectorAll(craftcrawlSubtabNavSelector).forEach(function (nav) {
            navs.push(nav);
        });
    }

    return navs;
}

function craftcrawlWatchSubtabNav(nav) {
    if (!craftcrawlSubtabResizeObserver || craftcrawlObservedSubtabNavs.has(nav)) return;
    craftcrawlObservedSubtabNavs.add(nav);
    craftcrawlSubtabResizeObserver.observe(nav);
}

window.CraftCrawlInitSubtabThumbs = function (root) {
    var navs = craftcrawlFindSubtabNavs(root);
    navs.forEach(craftcrawlWatchSubtabNav);

    requestAnimationFrame(function () {
        navs.forEach(function (nav) {
            craftcrawlUpdateSubtabThumb(nav, false);
        });
    });
};

window.CraftCrawlInitBusinessSubtabs = function (root = document) {
    var page = root.querySelector('.business-details-page') || root.querySelector('.business-portal');
    if (!page || page.dataset.businessSubtabsReady === 'true') return;
    page.dataset.businessSubtabsReady = 'true';

    var navs = page.querySelectorAll('.business-subtab-nav');

    function switchTab(nav, target) {
        nav.querySelectorAll('[data-business-subtab]').forEach(function (tab) {
            var isTarget = tab.dataset.businessSubtab === target;
            tab.classList.toggle('is-active', isTarget);
            tab.setAttribute('aria-selected', isTarget ? 'true' : 'false');
        });

        page.querySelectorAll('[data-business-subtab-panel]').forEach(function (panel) {
            panel.hidden = panel.dataset.businessSubtabPanel !== target;
        });

        page.querySelectorAll('input[name="current_tab"]').forEach(function (input) {
            input.value = target;
        });

        craftcrawlUpdateSubtabThumb(nav, true);

        var url = new URL(window.location.href);
        if (target === 'info' || target === 'overview') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', target);
        }
        history.replaceState(null, '', url.toString());
    }

    page.addEventListener('click', function (e) {
        var tab = e.target.closest('[data-business-subtab]');
        if (!tab) return;
        var nav = tab.closest('.business-subtab-nav');
        if (nav) switchTab(nav, tab.dataset.businessSubtab);
    });

    var urlParams = new URLSearchParams(window.location.search);
    var initialTab = urlParams.get('tab');
    navs.forEach(function (nav) {
        if (initialTab && nav.querySelector('[data-business-subtab="' + initialTab + '"]')) {
            switchTab(nav, initialTab);
        }
        requestAnimationFrame(function () {
            craftcrawlUpdateSubtabThumb(nav, false);
        });
    });

};

CraftCrawlInitBusinessSubtabs();
CraftCrawlInitSubtabThumbs();

var craftcrawlSubtabResizeTimer;
window.addEventListener('resize', function () {
    clearTimeout(craftcrawlSubtabResizeTimer);
    craftcrawlSubtabResizeTimer = setTimeout(function () {
        craftcrawlFindSubtabNavs(document).forEach(function (nav) {
            nav.classList.remove('is-subtab-thumb-ready');
            craftcrawlUpdateSubtabThumb(nav, false);
        });
    }, 150);
});

window.addEventListener('craftcrawl:user-tab-changed', function () {
    CraftCrawlInitSubtabThumbs();
});

document.addEventListener('craftcrawl:user-shell-navigated', function (event) {
    CraftCrawlInitSubtabThumbs(event.target);
});

window.addEventListener('pageshow', function () {
    CraftCrawlInitSubtabThumbs();
});

window.addEventListener('load', function () {
    CraftCrawlInitSubtabThumbs();
});

if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(function () {
        CraftCrawlInitSubtabThumbs();
    });
}

if (typeof MutationObserver === 'function') {
    var craftcrawlSubtabMutationObserver = new MutationObserver(function (records) {
        records.forEach(function (record) {
            if (record.type === 'attributes') {
                var revealedRoot = record.target;
                if (!revealedRoot.hidden
                    && (revealedRoot.matches(craftcrawlSubtabNavSelector)
                        || revealedRoot.querySelector(craftcrawlSubtabNavSelector))) {
                    CraftCrawlInitSubtabThumbs(revealedRoot);
                }
                return;
            }

            record.addedNodes.forEach(function (node) {
                if (!(node instanceof Element)) return;
                if (node.matches(craftcrawlSubtabNavSelector) || node.querySelector(craftcrawlSubtabNavSelector)) {
                    CraftCrawlInitSubtabThumbs(node);
                }
            });
        });
    });
    craftcrawlSubtabMutationObserver.observe(document.documentElement, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['hidden']
    });
}
