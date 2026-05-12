const savedCraftCrawlPalette = localStorage.getItem('craftcrawl_palette');
const renamedCraftCrawlPalettes = {
    'craft-night': 'ember',
    'ember-room': 'ember',
    'night-dark': 'ember-dark'
};
const craftCrawlPalette = renamedCraftCrawlPalettes[savedCraftCrawlPalette] || savedCraftCrawlPalette || 'trail-map';

document.documentElement.dataset.palette = craftCrawlPalette;

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
