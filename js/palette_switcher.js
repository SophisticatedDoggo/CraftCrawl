const paletteButtons = document.querySelectorAll('[data-palette-option]');
const activePalette = document.documentElement.dataset.palette || 'trail-map';

function setPalette(palette) {
    document.documentElement.dataset.palette = palette;
    localStorage.setItem('craftcrawl_palette', palette);
    document.cookie = `craftcrawl_account_palette=${encodeURIComponent(palette)}; path=/; max-age=31536000; samesite=lax`;

    if (window.syncCraftCrawlNativeStatusBar) {
        window.syncCraftCrawlNativeStatusBar();
    }

    paletteButtons.forEach((button) => {
        const isActive = button.dataset.paletteOption === palette;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', String(isActive));
    });
}

paletteButtons.forEach((button) => {
    button.addEventListener('click', () => {
        setPalette(button.dataset.paletteOption);
    });
});

setPalette(activePalette);
