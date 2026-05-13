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
});
