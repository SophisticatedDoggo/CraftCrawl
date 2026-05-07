const script = document.getElementById('search-js');
// wait for the Mapbox Search JS script to load before using it
script.onload = function () {
    mapboxsearch.config.accessToken = window.MAPBOX_ACCESS_TOKEN;

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

        document.getElementById('longitude').value = longitude;
        document.getElementById('latitude').value = latitude;
    });
};
