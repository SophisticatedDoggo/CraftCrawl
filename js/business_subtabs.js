var craftcrawlSubtabNavSelector = [
    '.business-subtab-nav',
    '.profile-subtab-nav',
    '.quest-subtab-nav',
    '.friends-subtab-nav'
].join(', ');

function craftcrawlUpdateSubtabThumb(nav, animate) {
    if (!nav) return;
    var active = nav.querySelector('.is-active');
    if (!active) {
        nav.style.setProperty('--subtab-thumb-opacity', '0');
        return;
    }

    var navRect = nav.getBoundingClientRect();
    var btnRect = active.getBoundingClientRect();
    if (!navRect.width || !btnRect.width) return;

    if (animate && !nav.classList.contains('is-subtab-thumb-ready')) {
        nav.classList.add('is-subtab-thumb-ready');
    }

    nav.style.setProperty('--subtab-thumb-left', (btnRect.left - navRect.left) + 'px');
    nav.style.setProperty('--subtab-thumb-top', (btnRect.top - navRect.top) + 'px');
    nav.style.setProperty('--subtab-thumb-width', btnRect.width + 'px');
    nav.style.setProperty('--subtab-thumb-height', btnRect.height + 'px');
    nav.style.setProperty('--subtab-thumb-opacity', '1');
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

window.CraftCrawlInitSubtabThumbs = function (root) {
    requestAnimationFrame(function () {
        craftcrawlFindSubtabNavs(root).forEach(function (nav) {
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
