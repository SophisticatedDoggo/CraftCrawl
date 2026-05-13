function getCraftCrawlCookie(name) {
    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith(`${name}=`));

    return cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : null;
}

const savedCraftCrawlPalette = localStorage.getItem('craftcrawl_palette');
const accountCraftCrawlPalette = getCraftCrawlCookie('craftcrawl_account_palette');
const renamedCraftCrawlPalettes = {
    'craft-night': 'ember',
    'ember-room': 'ember',
    'night-dark': 'ember-dark'
};
const craftCrawlPalette = renamedCraftCrawlPalettes[accountCraftCrawlPalette]
    || accountCraftCrawlPalette
    || renamedCraftCrawlPalettes[savedCraftCrawlPalette]
    || savedCraftCrawlPalette
    || 'trail-map';

document.documentElement.dataset.palette = craftCrawlPalette;
localStorage.setItem('craftcrawl_palette', craftCrawlPalette);

function getCraftCrawlNativePlugin(name) {
    const capacitor = window.Capacitor;
    const plugins = capacitor && capacitor.Plugins;
    const existingPlugin = plugins && plugins[name];
    const isNative = capacitor
        && typeof capacitor.isNativePlatform === 'function'
        && capacitor.isNativePlatform();

    if (existingPlugin) {
        return existingPlugin;
    }

    if (!isNative
        || typeof capacitor.registerPlugin !== 'function'
        || (capacitor.isPluginAvailable && !capacitor.isPluginAvailable(name))) {
        return null;
    }

    return capacitor.registerPlugin(name);
}

function syncCraftCrawlNativeStatusBar() {
    const statusBar = getCraftCrawlNativePlugin('StatusBar');

    if (!statusBar) {
        return;
    }

    const isDarkPalette = ['trail-dark', 'ember-dark'].includes(document.documentElement.dataset.palette);

    if (typeof statusBar.setOverlaysWebView === 'function') {
        statusBar.setOverlaysWebView({ overlay: false }).catch(() => {});
    }

    if (typeof statusBar.setStyle === 'function') {
        statusBar.setStyle({ style: isDarkPalette ? 'DARK' : 'LIGHT' }).catch(() => {});
    }

    if (typeof statusBar.setBackgroundColor === 'function') {
        statusBar.setBackgroundColor({ color: isDarkPalette ? '#000000' : '#ffffff' }).catch(() => {});
    }
}

window.syncCraftCrawlNativeStatusBar = syncCraftCrawlNativeStatusBar;

let craftCrawlPageLoaderTimer = null;
let craftCrawlPageTransitionStarted = false;

function ensureCraftCrawlPageLoader() {
    let loader = document.querySelector('[data-page-loader]');

    if (loader) {
        return loader;
    }

    loader = document.createElement('div');
    loader.className = 'page-loader';
    loader.setAttribute('data-page-loader', '');
    loader.setAttribute('aria-hidden', 'true');
    loader.innerHTML = '<div class="page-loader-spinner" aria-label="Loading"></div>';

    document.body.appendChild(loader);
    return loader;
}

function showCraftCrawlPageLoader(delay) {
    window.clearTimeout(craftCrawlPageLoaderTimer);

    craftCrawlPageLoaderTimer = window.setTimeout(function () {
        const loader = ensureCraftCrawlPageLoader();
        loader.classList.add('is-visible');
        loader.setAttribute('aria-hidden', 'false');
    }, delay ?? 120);
}

function hideCraftCrawlPageLoader() {
    window.clearTimeout(craftCrawlPageLoaderTimer);

    const loader = document.querySelector('[data-page-loader]');
    if (!loader) {
        return;
    }

    loader.classList.remove('is-visible');
    loader.setAttribute('aria-hidden', 'true');
}

function shouldShowCraftCrawlPageLoaderForLink(link, event) {
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    if (link.hasAttribute('download')) {
        return false;
    }

    const href = link.getAttribute('href') || '';
    if (href === '' || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) {
        return false;
    }

    const destination = new URL(link.href, window.location.href);
    if (destination.origin !== window.location.origin) {
        return false;
    }

    return destination.pathname !== window.location.pathname
        || destination.search !== window.location.search;
}

function continueCraftCrawlLinkNavigation(link) {
    const destination = link.href;

    window.requestAnimationFrame(function () {
        window.setTimeout(function () {
            window.location.assign(destination);
        }, 90);
    });
}

function lockCraftCrawlMobileViewport() {
    const viewport = document.querySelector('meta[name="viewport"]');

    if (!viewport) {
        return;
    }

    const content = viewport.getAttribute('content') || '';
    const rules = content
        .split(',')
        .map((rule) => rule.trim())
        .filter(Boolean);
    const nextRules = rules.filter((rule) => {
        const key = rule.split('=')[0].trim();
        return !['maximum-scale', 'user-scalable', 'viewport-fit'].includes(key);
    });

    nextRules.push('maximum-scale=1.0', 'user-scalable=no', 'viewport-fit=cover');
    viewport.setAttribute('content', nextRules.join(', '));
}

function resetCraftCrawlMobileViewportScroll() {
    document.documentElement.scrollLeft = 0;
    document.body.scrollLeft = 0;
    window.scrollTo(0, window.scrollY);
}

lockCraftCrawlMobileViewport();
syncCraftCrawlNativeStatusBar();

document.addEventListener('focusout', function (event) {
    const field = event.target;

    if (!(field instanceof HTMLInputElement)
        && !(field instanceof HTMLTextAreaElement)
        && !(field instanceof HTMLSelectElement)) {
        return;
    }

    window.setTimeout(resetCraftCrawlMobileViewportScroll, 250);
});

document.addEventListener('submit', function (event) {
    if (event.defaultPrevented) {
        return;
    }

    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const submitter = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');

    if (!submitter || submitter.disabled) {
        return;
    }

    submitter.classList.add('is-loading');
    submitter.setAttribute('aria-busy', 'true');

    if (!form.target || form.target === '_self') {
        window.setTimeout(function () {
            if (!event.defaultPrevented) {
                showCraftCrawlPageLoader(180);
            }
        }, 0);
    }
});

document.addEventListener('click', function (event) {
    const link = event.target.closest && event.target.closest('a[href]');

    if (!shouldShowCraftCrawlPageLoaderForLink(link, event)) {
        return;
    }

    if (craftCrawlPageTransitionStarted) {
        event.preventDefault();
        return;
    }

    craftCrawlPageTransitionStarted = true;
    event.preventDefault();
    showCraftCrawlPageLoader(0);
    continueCraftCrawlLinkNavigation(link);
});

window.addEventListener('pageshow', function () {
    craftCrawlPageTransitionStarted = false;
    hideCraftCrawlPageLoader();
});
window.addEventListener('pagehide', function () {
    showCraftCrawlPageLoader(0);
});
