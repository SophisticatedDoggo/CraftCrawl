let craftcrawlPaletteSaveRequestId = 0;

function showPaletteStatus(form, message, isError) {
    const status = form ? form.parentElement.querySelector('[data-palette-status]') : null;
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('form-message-error', isError);
    status.classList.toggle('form-message-success', !isError);
    status.hidden = false;
}

function setPalette(palette, buttons = document.querySelectorAll('[data-palette-option]')) {
    document.documentElement.dataset.palette = palette;
    localStorage.setItem('craftcrawl_palette', palette);
    document.cookie = `craftcrawl_account_palette=${encodeURIComponent(palette)}; path=/; max-age=31536000; samesite=lax`;
    window.syncCraftCrawlNativeStatusBar?.();
    window.syncCraftCrawlLogos?.();
    buttons.forEach((button) => {
        const isActive = button.dataset.paletteOption === palette;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', String(isActive));
    });
}

window.CraftCrawlInitPaletteSwitcher = function (root = document) {
    const paletteButtons = root.querySelectorAll('[data-palette-option]');
    if (!paletteButtons.length) return;
    const activePalette = document.documentElement.dataset.palette || 'trail-map';
    paletteButtons.forEach((button) => {
        if (button.dataset.paletteReady === 'true') return;
        button.dataset.paletteReady = 'true';
        button.addEventListener('click', (event) => {
            const form = button.form;
            const previousPalette = document.documentElement.dataset.palette || activePalette;
            const nextPalette = button.dataset.paletteOption;
            if (!form || !nextPalette) return;
            event.preventDefault();
            craftcrawlPaletteSaveRequestId += 1;
            const requestId = craftcrawlPaletteSaveRequestId;
            setPalette(nextPalette);
            showPaletteStatus(form, 'Saving display theme...', false);
            const formData = new FormData(form);
            formData.set('display_palette', nextPalette);
            fetch(form.action || window.location.href, { method: 'POST', body: formData, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then((response) => { if (!response.ok) throw new Error('Display theme could not be saved.'); return response.json(); })
                .then((data) => {
                    if (requestId !== craftcrawlPaletteSaveRequestId) return;
                    if (!data.ok) throw new Error(data.message || 'Display theme could not be saved.');
                    setPalette(data.palette || nextPalette);
                    showPaletteStatus(form, data.message || 'Display theme updated.', false);
                })
                .catch((error) => {
                    if (requestId !== craftcrawlPaletteSaveRequestId) return;
                    setPalette(previousPalette);
                    showPaletteStatus(form, error.message || 'Display theme could not be saved.', true);
                });
        });
    });
    setPalette(activePalette);
};
window.CraftCrawlInitPaletteSwitcher();
