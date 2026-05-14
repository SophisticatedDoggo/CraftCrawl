const paletteButtons = document.querySelectorAll('[data-palette-option]');
const activePalette = document.documentElement.dataset.palette || 'trail-map';
let paletteSaveRequestId = 0;

function showPaletteStatus(form, message, isError) {
    const status = form ? form.parentElement.querySelector('[data-palette-status]') : null;

    if (!status) {
        return;
    }

    status.textContent = message;
    status.classList.toggle('form-message-error', isError);
    status.classList.toggle('form-message-success', !isError);
    status.hidden = false;
}

function setPalette(palette, options = {}) {
    document.documentElement.dataset.palette = palette;
    localStorage.setItem('craftcrawl_palette', palette);
    document.cookie = `craftcrawl_account_palette=${encodeURIComponent(palette)}; path=/; max-age=31536000; samesite=lax`;

    if (window.syncCraftCrawlNativeStatusBar) {
        window.syncCraftCrawlNativeStatusBar();
    }

    if (window.syncCraftCrawlLogos) {
        window.syncCraftCrawlLogos();
    }

    paletteButtons.forEach((button) => {
        const isActive = button.dataset.paletteOption === palette;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', String(isActive));
    });
}

paletteButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
        const form = button.form;
        const previousPalette = document.documentElement.dataset.palette || activePalette;
        const nextPalette = button.dataset.paletteOption;

        if (!form || !nextPalette) {
            return;
        }

        event.preventDefault();
        paletteSaveRequestId += 1;
        const requestId = paletteSaveRequestId;
        setPalette(nextPalette);
        showPaletteStatus(form, 'Saving display theme...', false);

        const formData = new FormData(form);
        formData.set('display_palette', nextPalette);

        fetch(form.action || window.location.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Display theme could not be saved.');
                }

                return response.json();
            })
            .then((data) => {
                if (requestId !== paletteSaveRequestId) {
                    return;
                }

                if (!data.ok) {
                    throw new Error(data.message || 'Display theme could not be saved.');
                }

                setPalette(data.palette || nextPalette);
                showPaletteStatus(form, data.message || 'Display theme updated.', false);
            })
            .catch((error) => {
                if (requestId !== paletteSaveRequestId) {
                    return;
                }

                setPalette(previousPalette);
                showPaletteStatus(form, error.message || 'Display theme could not be saved.', true);
            });
    });
});

setPalette(activePalette);
