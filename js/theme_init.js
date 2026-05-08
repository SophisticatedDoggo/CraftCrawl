const savedCraftCrawlPalette = localStorage.getItem('craftcrawl_palette');
const renamedCraftCrawlPalettes = {
    'craft-night': 'ember',
    'ember-room': 'ember',
    'night-dark': 'ember-dark'
};
const craftCrawlPalette = renamedCraftCrawlPalettes[savedCraftCrawlPalette] || savedCraftCrawlPalette || 'trail-map';

document.documentElement.dataset.palette = craftCrawlPalette;
