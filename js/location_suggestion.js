(function () {
    const searchJsUrl = 'https://api.mapbox.com/search-js/v1.5.0/web.js';
    let initialized = false;
    let loadingScript = false;

    function ensureSearchJs() {
        const existingScript = document.getElementById('search-js');

        if (window.mapboxsearch) {
            initLocationSuggestionAutofill();
            return;
        }

        if (existingScript) {
            existingScript.addEventListener('load', initLocationSuggestionAutofill, { once: true });
            return;
        }

        if (loadingScript) {
            return;
        }

        loadingScript = true;
        const script = document.createElement('script');
        script.id = 'search-js';
        script.src = searchJsUrl;
        script.defer = true;
        script.addEventListener('load', initLocationSuggestionAutofill, { once: true });
        document.head.appendChild(script);
    }

    function getContextValue(context, type, preferredKeys) {
        if (Array.isArray(context)) {
            const item = context.find((entry) => entry && entry.id && entry.id.startsWith(`${type}.`));
            if (!item) return '';
            for (const key of preferredKeys) {
                if (item[key]) return item[key];
            }
            return '';
        }

        const item = context && context[type];
        if (!item) return '';
        for (const key of preferredKeys) {
            if (item[key]) return item[key];
        }
        return '';
    }

    function initLocationSuggestionAutofill() {
        if (initialized || !window.mapboxsearch) return;
        initialized = true;

        window.mapboxsearch.config.accessToken = window.MAPBOX_ACCESS_TOKEN;

        const street = document.getElementById('street_address');
        const city = document.getElementById('city');
        const state = document.getElementById('state');
        const zip = document.getElementById('zip');
        const latitude = document.getElementById('latitude');
        const longitude = document.getElementById('longitude');
        const mapboxPlaceId = document.getElementById('mapbox_place_id');

        if (!street || !city || !state || !zip || !latitude || !longitude || !mapboxPlaceId) {
            return;
        }

        let selectedAddress = '';
        let selectedResult = null;
        const autofill = window.mapboxsearch.autofill({
            options: {
                country: 'us',
                limit: 10
            }
        });

        autofill.addEventListener('retrieve', function (event) {
            const feature = event.detail && event.detail.features && event.detail.features[0];
            if (!feature || !feature.geometry || !feature.geometry.coordinates) return;

            const properties = feature.properties || {};
            const context = properties.context || {};
            const [selectedLongitude, selectedLatitude] = feature.geometry.coordinates;
            const selectedMapboxPlaceId = properties.mapbox_id || (properties.action && properties.action.id) || feature.id || '';

            selectedAddress = properties.address_line1 || properties.full_address || properties.name || '';
            selectedResult = {
                address: selectedAddress,
                longitude: selectedLongitude ?? '',
                latitude: selectedLatitude ?? '',
                mapboxPlaceId: selectedMapboxPlaceId
            };

            longitude.value = selectedResult.longitude;
            latitude.value = selectedResult.latitude;
            mapboxPlaceId.value = selectedResult.mapboxPlaceId;
            street.value = selectedAddress;
            city.value = properties.address_level2 || getContextValue(context, 'place', ['name', 'text']) || properties.place || '';
            state.value = properties.address_level1 || getContextValue(context, 'region', ['region_code', 'name', 'text']) || properties.region || '';
            zip.value = getContextValue(context, 'postcode', ['name', 'text']) || properties.postcode || '';
        });

        street.addEventListener('input', function () {
            if (street.value !== selectedAddress) {
                selectedResult = null;
                latitude.value = '';
                longitude.value = '';
                mapboxPlaceId.value = '';
                city.value = '';
                state.value = '';
                zip.value = '';
            }
        });

        const form = street.form;
        if (form) {
            form.addEventListener('submit', function () {
                if (!selectedResult || street.value !== selectedResult.address) return;
                latitude.value = selectedResult.latitude;
                longitude.value = selectedResult.longitude;
                mapboxPlaceId.value = selectedResult.mapboxPlaceId;
            });
        }
    }

    const form = document.querySelector('.business-add-location-form');
    const toggle = document.querySelector('.business-manual-location-toggle');

    if (form?.classList.contains('is-visible') || toggle?.open) {
        ensureSearchJs();
    }

    if (toggle) {
        toggle.addEventListener('toggle', function () {
            if (toggle.open) {
                ensureSearchJs();
            }
        });
    }
})();
