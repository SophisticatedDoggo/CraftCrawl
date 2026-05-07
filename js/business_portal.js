const script = document.getElementById('search-js');

script.onload = function () {
    mapboxsearch.config.accessToken = window.MAPBOX_ACCESS_TOKEN;
    const streetAddressInput = document.getElementById('street_address');
    let selectedAddress = streetAddressInput ? streetAddressInput.value : '';

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
    });

    if (streetAddressInput) {
        streetAddressInput.addEventListener('input', function () {
            if (streetAddressInput.value !== selectedAddress) {
                document.getElementById('longitude').value = '';
                document.getElementById('latitude').value = '';
                document.getElementById('city').value = '';
                document.getElementById('state').value = '';
                document.getElementById('zip').value = '';
            }
        });
    }
};
