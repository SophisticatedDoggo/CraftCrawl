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
const craftCrawlPaletteLogos = {
    'trail-map': 'craft-crawl-logo-trail.png',
    'trail-dark': 'craft-crawl-logo-trail-dark.png',
    ember: 'craft-crawl-logo-ember.png',
    'ember-dark': 'craft-crawl-logo-ember-dark.png'
};
const craftCrawlPaletteAppIcons = {
    'trail-map': 'trail',
    'trail-dark': 'trail-dark',
    ember: 'ember',
    'ember-dark': 'ember-dark'
};
const craftCrawlPaletteBackgrounds = {
    'trail-map': '#f4f1ea',
    'trail-dark': '#15171a',
    ember: '#f7f3ed',
    'ember-dark': '#15171a'
};

document.documentElement.dataset.palette = craftCrawlPalette;
document.documentElement.style.backgroundColor = craftCrawlPaletteBackgrounds[craftCrawlPalette]
    || craftCrawlPaletteBackgrounds['trail-map'];
localStorage.setItem('craftcrawl_palette', craftCrawlPalette);

function isCraftCrawlNativeApp() {
    const capacitor = window.Capacitor;
    return Boolean(
        capacitor
        && typeof capacitor.isNativePlatform === 'function'
        && capacitor.isNativePlatform()
    );
}

function isCraftCrawlInternalLink(link) {
    if (!(link instanceof HTMLAnchorElement)) {
        return false;
    }

    const href = link.getAttribute('href') || '';
    if (
        href === ''
        || href.startsWith('#')
        || href.startsWith('mailto:')
        || href.startsWith('tel:')
        || href.startsWith('javascript:')
    ) {
        return false;
    }

    try {
        return new URL(link.href, window.location.href).origin === window.location.origin;
    } catch (error) {
        return false;
    }
}

function syncCraftCrawlNativeInternalLinks(root = document) {
    root.querySelectorAll('a[href]').forEach((link) => {
        link.classList.toggle('native-internal-link', isCraftCrawlInternalLink(link));
    });
}

function suppressCraftCrawlNativeInternalLinkCallouts(event) {
    const target = event.target instanceof Element ? event.target : null;
    const link = target ? target.closest('a[href]') : null;
    if (!isCraftCrawlInternalLink(link)) {
        return;
    }

    event.preventDefault();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-back-link]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (window.history.length <= 1) {
                return;
            }

            event.preventDefault();
            window.history.back();
        });
    });

    if (isCraftCrawlNativeApp()) {
        document.documentElement.classList.add('native-app');
        syncCraftCrawlNativeInternalLinks();
        document.addEventListener('contextmenu', suppressCraftCrawlNativeInternalLinkCallouts);

        new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof Element)) {
                        return;
                    }

                    if (node.matches('a[href]')) {
                        node.classList.toggle('native-internal-link', isCraftCrawlInternalLink(node));
                    }

                    syncCraftCrawlNativeInternalLinks(node);
                });
            });
        }).observe(document.body, {
            childList: true,
            subtree: true
        });
    }
});

function craftCrawlLogoUrlFromExisting(existingSrc, logoFile) {
    try {
        const url = new URL(existingSrc || 'images/Logo.webp', window.location.href);
        url.pathname = url.pathname.replace(/[^/]*$/, logoFile);
        url.search = '';
        url.hash = '';
        return url.href;
    } catch (error) {
        return `images/${logoFile}`;
    }
}

function syncCraftCrawlLogos() {
    const palette = document.documentElement.dataset.palette || 'trail-map';
    const logoFile = craftCrawlPaletteLogos[palette] || craftCrawlPaletteLogos['trail-map'];

    document.querySelectorAll('img.site-logo').forEach((logo) => {
        const nextSrc = craftCrawlLogoUrlFromExisting(logo.getAttribute('src'), logoFile);
        if (logo.src !== nextSrc) {
            logo.src = nextSrc;
        }
    });
}

window.syncCraftCrawlLogos = syncCraftCrawlLogos;

function syncCraftCrawlNativeAppIcon(palette) {
    const appIcon = getCraftCrawlNativePlugin('CraftCrawlAppIcon');
    const iconName = craftCrawlPaletteAppIcons[palette] || craftCrawlPaletteAppIcons['trail-map'];

    if (!appIcon || typeof appIcon.setIcon !== 'function') {
        return;
    }

    appIcon.setIcon({ name: iconName })
        .then(() => {
            localStorage.setItem('craftcrawl_native_app_icon', iconName);
        })
        .catch(() => {});
}

window.syncCraftCrawlNativeAppIcon = syncCraftCrawlNativeAppIcon;

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

    if (!isNative || typeof capacitor.registerPlugin !== 'function') {
        return null;
    }

    return capacitor.registerPlugin(name);
}

window.getCraftCrawlNativePlugin = getCraftCrawlNativePlugin;

function getCraftCrawlCurrentPageBackground() {
    const palette = document.documentElement.dataset.palette || 'trail-map';
    return craftCrawlPaletteBackgrounds[palette] || craftCrawlPaletteBackgrounds['trail-map'];
}

function showCraftCrawlNativePageTransition() {
    const transition = getCraftCrawlNativePlugin('CraftCrawlPageTransition');

    if (!transition || typeof transition.show !== 'function') {
        return Promise.resolve();
    }

    return transition.show({ color: getCraftCrawlCurrentPageBackground() }).catch(() => {});
}

function hideCraftCrawlNativePageTransition() {
    const transition = getCraftCrawlNativePlugin('CraftCrawlPageTransition');

    if (!transition || typeof transition.hide !== 'function') {
        return;
    }

    transition.hide().catch(() => {});
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

function startCraftCrawlPageTransition(destination) {
    const destinationUrl = new URL(destination, window.location.href);

    if (destinationUrl.origin !== window.location.origin) {
        window.location.assign(destinationUrl.href);
        return;
    }

    if (destinationUrl.pathname === window.location.pathname
        && destinationUrl.search === window.location.search) {
        window.location.assign(destinationUrl.href);
        return;
    }

    if (craftCrawlPageTransitionStarted) {
        return;
    }

    craftCrawlPageTransitionStarted = true;
    showCraftCrawlPageLoader(180);
    showCraftCrawlNativePageTransition().finally(() => {
        window.location.assign(destinationUrl.href);
    });
}

function continueCraftCrawlLinkNavigation(link) {
    startCraftCrawlPageTransition(link.href);
}

window.CraftCrawlNavigateWithLoader = startCraftCrawlPageTransition;

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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncCraftCrawlLogos);
} else {
    syncCraftCrawlLogos();
}

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
                showCraftCrawlNativePageTransition();
            }
        }, 0);
    }
});

document.addEventListener('click', function (event) {
    const link = event.target.closest && event.target.closest('a[href]');

    if (!shouldShowCraftCrawlPageLoaderForLink(link, event)) {
        return;
    }

    event.preventDefault();
    continueCraftCrawlLinkNavigation(link);
});

window.addEventListener('pageshow', function () {
    craftCrawlPageTransitionStarted = false;
    hideCraftCrawlPageLoader();
    hideCraftCrawlNativePageTransition();
});
window.addEventListener('pagehide', function () {
    showCraftCrawlPageLoader(0);
    showCraftCrawlNativePageTransition();
});
