mapboxgl.accessToken = window.MAPBOX_ACCESS_TOKEN;
// creates the map, setting the container to the id of the div you added in step 2, and setting the initial center and zoom level of the map
const map = new mapboxgl.Map({
    container: 'map', // container ID
    style: 'mapbox://styles/mapbox/standard',
    config: {
        basemap: {
            showPlaceLabels: true,
            showPointOfInterestLabels: false,
            fuelingStationModePointOfInterestLabels: 'none',
            showIndoorLabels: false
        }
    },
    center: [-79.3432615, 40.208976], // starting position [lng, lat]
    zoom: 6.5, // starting zoom
    maxBounds: [
        [-80.330658, 39.759134], // Southwest [lng, lat]
        [-78.548126, 40.698535] // Northeast [lng, lat]
    ]
});

map.on('load', function () {
    //place object we will add our events to
    map.addSource('places',{
        'type': 'geojson',
        'data': {
            'type': 'FeatureCollection',
            'features': [] //data of businesses
        }
    });
    //add place object to map
    map.addLayer({
        id: 'place-badges',
        type: 'circle',
        source: 'places',
        paint: {
            'circle-radius': 11,
            'circle-color': '#ffffff',
            'circle-stroke-width': 3,
            'circle-stroke-color': [
                'match',
                ['get', 'businessType'],
                'brewery', '#f97316',
                'winery', '#9333ea',
                'cidery', '#16a34a',
                'distillery', '#2563eb',
                '#6b7280'
            ]
        }
    });

    map.addLayer({
        id: 'places',
        type: 'symbol',
        source: 'places',
        layout: {
            'text-field': ['get', 'listNumber'],
            'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
            'text-size': 14,
            'text-allow-overlap': true,
            'text-ignore-placement': true
        },
        paint: {
            'text-color': [
                'match',
                ['get', 'businessType'],
                'brewery', '#f97316',
                'winery', '#9333ea',
                'cidery', '#16a34a',
                'distillery', '#2563eb',
                '#6b7280'
            ],
            'text-halo-color': '#ffffff',
            'text-halo-width': 1
        }
    });

    map.addLayer({
        id: 'place-titles',
        type: 'symbol',
        source: 'places',
        minzoom: 11,
        layout: {
            'text-field': ['get', 'title'],
            'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
            'text-size': 13,
            'text-offset': [0, -1.5],
            'text-halo-color': '#ffffff',
            'text-halo-width': 2,
            'text-halo-blur': 0.5,
            'text-anchor': 'bottom',
            'text-allow-overlap': true,
            'text-ignore-placement': true
        },
        paint: {
            'text-color': [
                'match',
                ['get', 'businessType'],
                'brewery', '#f97316',
                'winery', '#9333ea',
                'cidery', '#16a34a',
                'distillery', '#2563eb',
                '#6b7280'
            ],
            'text-halo-color': '#ffffff',
            'text-halo-width': 1
        }
    });


    //Handle popups
    map.on('click', 'places', (e) => {
        const coordinates = e.features[0].geometry.coordinates.slice();
        const properties = e.features[0].properties;
        const popupHTML = `
            <strong>${properties.title}</strong>
            <p>${formatBusinessType(properties.businessType)} &middot; #${properties.listNumber} on map</p>
            <p>
                ${properties.streetAddress}<br>
                ${properties.city}, ${properties.state} ${properties.zip}
            </p>
            <a href="../business_details.php?id=${properties.id}">Open details</a>
        `;

        new mapboxgl.Popup()
            .setLngLat(coordinates)
            .setHTML(popupHTML)
            .addTo(map);
    });


    //Handle pointer styles
    map.on('mouseenter', 'places', () => {
        map.getCanvas().style.cursor = 'pointer';
    });
    map.on('mouseleave', 'places', () => {
        map.getCanvas().style.cursor = '';
    });

    //get our data from php function
    getAllLocations();
});

function formatBusinessType(type) {
    const labels = {
        brewery: 'Brewery',
        winery: 'Winery',
        cidery: 'Cidery',
        distillery: 'Distillery'
    };

    return labels[type] || 'Business';
}

function getAllLocations(){
    $.ajax({
        url: "../mapbox/get_locations.php",
        dataType: "json",
        success: function (businessData) {
            const numberedBusinessData = businessData.map((feature, index) => ({
                ...feature,
                properties: {
                    ...feature.properties,
                    listNumber: String(index + 1)
                }
            }));

            console.log(numberedBusinessData)
            map.getSource('places').setData({
                    'type': 'FeatureCollection',
                    'features': numberedBusinessData
            });
            renderBusinessList(numberedBusinessData);
        },
        error: function (e) {
            console.log(e);
        }
    });
}

function renderBusinessList(features) {
    const listContainer = document.getElementById('business-list');

    if (!listContainer) {
        return;
    }

    listContainer.innerHTML = features.map((feature) => {
        const properties = feature.properties;

        return `
            <li class="business-list-item">
                <span class="business-list-number business-list-number-${properties.businessType}">
                    ${properties.listNumber}
                </span>
                <div class="business-list-details">
                    <strong>${properties.title}</strong>
                    <span>${formatBusinessType(properties.businessType)} &middot; ${properties.city}, ${properties.state}</span>
                </div>
                <a href="../business_details.php?id=${properties.id}">Open details</a>
            </li>
        `;
    }).join('');
}
