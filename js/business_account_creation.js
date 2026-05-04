const script = document.getElementById('search-js');
// wait for the Mapbox Search JS script to load before using it
script.onload = function () {
    mapboxsearch.config.accessToken = window.MAPBOX_ACCESS_TOKEN;

    const autofill = mapboxsearch.autofill({
        options: {
            country: 'us',
            limit: 10,
            bbox: [-80.001068, 39.789017, -78.774719, 40.482993]
        }
    });

    autofill.addEventListener('retrieve', function (event) {
        const feature = event.detail.features[0];

        if (!feature || !feature.geometry || !feature.geometry.coordinates) {
            return;
        }

        const [longitude, latitude] = feature.geometry.coordinates;

        document.getElementById('longitude').value = longitude;
        document.getElementById('latitude').value = latitude;
    });
};
