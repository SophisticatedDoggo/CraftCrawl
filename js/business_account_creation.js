function fillAddressFromGooglePlace(place, streetAddressInput) {
    if (!place || !place.geometry || !place.geometry.location) return false;

    const componentValue = (type, shortName = false) => {
        const component = (place.address_components || []).find((item) => item.types && item.types.includes(type));
        return component ? (shortName ? component.short_name : component.long_name) : '';
    };
    const streetNumber = componentValue('street_number');
    const route = componentValue('route');
    const streetAddress = [streetNumber, route].filter(Boolean).join(' ') || place.formatted_address || place.name || '';

    document.getElementById('longitude').value = place.geometry.location.lng();
    document.getElementById('latitude').value = place.geometry.location.lat();
    document.getElementById('street_address').value = streetAddress;
    document.getElementById('city').value = componentValue('locality') || componentValue('postal_town') || componentValue('administrative_area_level_3');
    document.getElementById('state').value = componentValue('administrative_area_level_1', true);
    document.getElementById('zip').value = componentValue('postal_code');

    const sourceProvider = document.getElementById('source_provider');
    const sourcePlaceId = document.getElementById('source_place_id') || document.getElementById('mapbox_place_id');
    const providerHours = document.getElementById('provider_hours_json');
    if (sourceProvider) sourceProvider.value = 'google';
    if (sourcePlaceId) sourcePlaceId.value = place.place_id || '';
    if (providerHours) providerHours.value = '';

    if (streetAddressInput) {
        streetAddressInput.dataset.selectedAddress = streetAddress;
    }

    return true;
}

function initGoogleAddressAutocomplete() {
    const streetAddressInput = document.getElementById('street_address');
    if (!streetAddressInput || !window.google?.maps?.places || streetAddressInput.dataset.googleAutocompleteReady === 'true' || streetAddressInput.dataset.mapboxAutocompleteReady === 'true') {
        return false;
    }

    streetAddressInput.dataset.googleAutocompleteReady = 'true';
    const autocomplete = new google.maps.places.Autocomplete(streetAddressInput, {
        componentRestrictions: { country: 'us' },
        fields: ['address_components', 'formatted_address', 'geometry', 'name', 'place_id'],
        types: ['address']
    });

    autocomplete.addListener('place_changed', () => {
        fillAddressFromGooglePlace(autocomplete.getPlace(), streetAddressInput);
    });

    streetAddressInput.addEventListener('input', function () {
        if (streetAddressInput.value !== streetAddressInput.dataset.selectedAddress) {
            document.getElementById('longitude').value = '';
            document.getElementById('latitude').value = '';
            document.getElementById('city').value = '';
            document.getElementById('state').value = '';
            document.getElementById('zip').value = '';
            const sourceProvider = document.getElementById('source_provider');
            const sourcePlaceId = document.getElementById('source_place_id') || document.getElementById('mapbox_place_id');
            const providerHours = document.getElementById('provider_hours_json');
            if (sourceProvider) sourceProvider.value = 'manual';
            if (sourcePlaceId) sourcePlaceId.value = '';
            if (providerHours) providerHours.value = '';
        }
    });

    return true;
}

const script = document.getElementById('search-js');
if (window.GOOGLE_MAPS_BROWSER_API_KEY) {
    const googleInitTimer = window.setInterval(() => {
        if (initGoogleAddressAutocomplete()) {
            window.clearInterval(googleInitTimer);
        }
    }, 150);
    window.setTimeout(() => window.clearInterval(googleInitTimer), 6000);
}

// wait for the Mapbox Search JS script to load before using it as a fallback
if (script) {
script.onload = function () {
    if (document.getElementById('street_address')?.dataset.googleAutocompleteReady === 'true') {
        return;
    }

    mapboxsearch.config.accessToken = window.MAPBOX_ACCESS_TOKEN;
    const streetAddressInput = document.getElementById('street_address');
    if (streetAddressInput) {
        streetAddressInput.dataset.mapboxAutocompleteReady = 'true';
    }
    let selectedAddress = '';

    const autofill = mapboxsearch.autofill({
        options: {
            country: 'us',
            limit: 10,
            bbox: [-80.330658, 39.759134, -78.548126, 40.698535]
        }
    });

    autofill.addEventListener('retrieve', function (event) {
        const feature = event.detail.features[0];

        if (!feature || !feature.geometry || !feature.geometry.coordinates) {
            return;
        }

        const [longitude, latitude] = feature.geometry.coordinates;
        const properties = feature.properties || {};
        const context = properties.context || {};

        document.getElementById('longitude').value = longitude;
        document.getElementById('latitude').value = latitude;

        if (properties.address_line1 || properties.full_address || properties.name) {
            selectedAddress = properties.address_line1 || properties.full_address || properties.name;
            document.getElementById('street_address').value = selectedAddress;
        }

        if ((context.place && context.place.name) || properties.place) {
            document.getElementById('city').value = (context.place && context.place.name) || properties.place;
        }

        if ((context.region && context.region.region_code) || (context.region && context.region.name) || properties.region) {
            document.getElementById('state').value = (context.region && context.region.region_code) || (context.region && context.region.name) || properties.region;
        }

        if ((context.postcode && context.postcode.name) || properties.postcode) {
            document.getElementById('zip').value = (context.postcode && context.postcode.name) || properties.postcode;
        }

        const providerHours = document.getElementById('provider_hours_json');
        if (providerHours) providerHours.value = '';
    });

    if (streetAddressInput) {
        streetAddressInput.addEventListener('input', function () {
            if (streetAddressInput.value !== selectedAddress) {
                document.getElementById('longitude').value = '';
                document.getElementById('latitude').value = '';
                document.getElementById('city').value = '';
                document.getElementById('state').value = '';
                document.getElementById('zip').value = '';
                const providerHours = document.getElementById('provider_hours_json');
                if (providerHours) providerHours.value = '';
            }
        });
    }
};
}
