window.CraftCrawlInitBusinessAddressAutofill = function (root = document) {
    const streetAddressInput = root.querySelector('#street_address');
    if (!streetAddressInput || streetAddressInput.dataset.addressAutofillReady === 'true' || !window.mapboxsearch) return;
    streetAddressInput.dataset.addressAutofillReady = 'true';
    mapboxsearch.config.accessToken = window.MAPBOX_ACCESS_TOKEN;
    let selectedAddress = streetAddressInput.value || '';
    const autofill = mapboxsearch.autofill({ options: { country: 'us', limit: 10, bbox: [-80.330658, 39.759134, -78.548126, 40.698535] } });
    autofill.addEventListener('retrieve', (event) => {
        const feature = event.detail.features[0];
        if (!feature?.geometry?.coordinates) return;
        const [longitude, latitude] = feature.geometry.coordinates;
        const properties = feature.properties || {};
        const context = properties.context || {};
        root.querySelector('#longitude').value = longitude;
        root.querySelector('#latitude').value = latitude;
        if (properties.address_line1 || properties.full_address || properties.name) {
            selectedAddress = properties.address_line1 || properties.full_address || properties.name;
            streetAddressInput.value = selectedAddress;
        }
        if ((context.place && context.place.name) || properties.place) root.querySelector('#city').value = (context.place && context.place.name) || properties.place;
        if ((context.region && context.region.region_code) || (context.region && context.region.name) || properties.region) root.querySelector('#state').value = (context.region && context.region.region_code) || (context.region && context.region.name) || properties.region;
        if ((context.postcode && context.postcode.name) || properties.postcode) root.querySelector('#zip').value = (context.postcode && context.postcode.name) || properties.postcode;
    });
    streetAddressInput.addEventListener('input', () => {
        if (streetAddressInput.value === selectedAddress) return;
        ['longitude','latitude','city','state','zip'].forEach((id) => { const input = root.querySelector(`#${id}`); if (input) input.value = ''; });
    });
};
window.CraftCrawlInitBusinessAddressAutofill();
