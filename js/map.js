let allBusinessFeatures = [];

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
        'cluster': true,
        'clusterMaxZoom': 9,
        'clusterRadius': 45,
        'data': {
            'type': 'FeatureCollection',
            'features': [] //data of businesses
        }
    });

    map.addLayer({
        id: 'cluster-rings',
        type: 'circle',
        source: 'places',
        filter: ['has', 'point_count'],
        maxzoom: 10,
        paint: {
            'circle-color': [
                'step',
                ['get', 'point_count'],
                '#f97316',
                5, '#9333ea',
                10, '#2563eb'
            ],
            'circle-radius': [
                'step',
                ['get', 'point_count'],
                24,
                5, 28,
                10, 32
            ],
            'circle-opacity': 0.2,
            'circle-stroke-width': 0
        }
    });

    map.addLayer({
        id: 'clusters',
        type: 'circle',
        source: 'places',
        filter: ['has', 'point_count'],
        maxzoom: 10,
        paint: {
            'circle-color': '#475569',
            'circle-radius': [
                'step',
                ['get', 'point_count'],
                17,
                5, 21,
                10, 25
            ],
            'circle-stroke-width': 3,
            'circle-stroke-color': '#ffffff'
        }
    });

    map.addLayer({
        id: 'cluster-count',
        type: 'symbol',
        source: 'places',
        filter: ['has', 'point_count'],
        maxzoom: 10,
        layout: {
            'text-field': ['concat', ['get', 'point_count_abbreviated'], '+'],
            'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
            'text-size': 13,
            'text-allow-overlap': true,
            'text-ignore-placement': true
        },
        paint: {
            'text-color': '#ffffff',
            'text-halo-color': '#111827',
            'text-halo-width': 1
        }
    });

    //add place object to map
    map.addLayer({
        id: 'place-badges',
        type: 'circle',
        source: 'places',
        filter: ['!', ['has', 'point_count']],
        paint: {
            'circle-radius': 15,
            'circle-color': [
                'match',
                ['get', 'businessType'],
                'brewery', '#f97316',
                'winery', '#9333ea',
                'cidery', '#dc2626',
                'distillery', '#2563eb',
                'meadery', '#facc15',
                '#6b7280'
            ],
            'circle-stroke-width': 3,
            'circle-stroke-color': '#ffffff'
        }
    });

    map.addLayer({
        id: 'places',
        type: 'symbol',
        source: 'places',
        filter: ['!', ['has', 'point_count']],
        layout: {
            'text-field': ['get', 'listNumber'],
            'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
            'text-size': 14,
            'text-allow-overlap': true,
            'text-ignore-placement': true
        },
        paint: {
            'text-color': '#ffffff',
            'text-halo-color': '#111827',
            'text-halo-width': 1
        }
    });

    map.addLayer({
        id: 'place-titles',
        type: 'symbol',
        source: 'places',
        filter: ['!', ['has', 'point_count']],
        layout: {
            'text-field': ['get', 'title'],
            'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
            'text-size': 13,
            'text-offset': [0, -1.5],
            'text-anchor': 'bottom',
            'text-allow-overlap': true,
            'text-ignore-placement': true
        },
        paint: {
            'text-color': [
                'match',
                ['get', 'businessType'],
                'brewery', '#c2410c',
                'winery', '#6b21a8',
                'cidery', '#991b1b',
                'distillery', '#1d4ed8',
                'meadery', '#a16207',
                '#6b7280'
            ],
            'text-halo-color': '#ffffff',
            'text-halo-width': 1
        }
    });


    //Handle popups
    map.on('click', 'clusters', (e) => {
        const features = map.queryRenderedFeatures(e.point, {
            layers: ['clusters']
        });
        const clusterId = features[0].properties.cluster_id;

        map.getSource('places').getClusterExpansionZoom(clusterId, (error, zoom) => {
            if (error) {
                return;
            }

            map.easeTo({
                center: features[0].geometry.coordinates,
                zoom: zoom
            });
        });
    });

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
    map.on('mouseenter', 'clusters', () => {
        map.getCanvas().style.cursor = 'pointer';
    });
    map.on('mouseleave', 'clusters', () => {
        map.getCanvas().style.cursor = '';
    });
    map.on('mouseenter', 'places', () => {
        map.getCanvas().style.cursor = 'pointer';
    });
    map.on('mouseleave', 'places', () => {
        map.getCanvas().style.cursor = '';
    });

    //get our data from php function
    getAllLocations();
    getAllEvents();
});

setupPortalTabs();

function formatBusinessType(type) {
    const labels = {
        brewery: 'Brewery',
        winery: 'Winery',
        cidery: 'Cidery',
        distillery: 'Distillery',
        meadery: 'Meadery'
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

            allBusinessFeatures = numberedBusinessData;
            console.log(numberedBusinessData)
            map.getSource('places').setData({
                    'type': 'FeatureCollection',
                    'features': numberedBusinessData
            });
            renderBusinessList(numberedBusinessData);
            setupBusinessSearch();
        },
        error: function (e) {
            console.log(e);
        }
    });
}

function setupBusinessSearch() {
    const searchForm = document.querySelector('.business-search');
    const searchInput = document.getElementById('business-search-input');
    const searchResults = document.getElementById('business-search-results');

    if (!searchForm || !searchInput || !searchResults || searchInput.dataset.ready === 'true') {
        return;
    }

    searchInput.dataset.ready = 'true';

    searchForm.addEventListener('submit', (event) => {
        event.preventDefault();

        const matches = getBusinessSearchMatches(searchInput.value);

        if (matches.length) {
            openBusinessDetails(matches[0].properties.id);
        }
    });

    searchInput.addEventListener('input', () => {
        renderBusinessSearchResults(getBusinessSearchMatches(searchInput.value));
    });

    document.addEventListener('click', (event) => {
        if (!searchForm.contains(event.target)) {
            searchResults.hidden = true;
        }
    });
}

function getBusinessSearchMatches(query) {
    const normalizedQuery = query.trim().toLowerCase();

    if (!normalizedQuery) {
        return [];
    }

    return allBusinessFeatures.filter((feature) => {
        const properties = feature.properties;
        const searchableText = [
            properties.title,
            formatBusinessType(properties.businessType),
            properties.city,
            properties.state
        ].join(' ').toLowerCase();

        return searchableText.includes(normalizedQuery);
    }).slice(0, 6);
}

function renderBusinessSearchResults(matches) {
    const searchResults = document.getElementById('business-search-results');

    if (!searchResults) {
        return;
    }

    searchResults.innerHTML = '';

    if (!matches.length) {
        searchResults.hidden = true;
        return;
    }

    matches.forEach((feature) => {
        const properties = feature.properties;
        const link = document.createElement('a');
        const name = document.createElement('strong');
        const meta = document.createElement('span');

        link.href = `../business_details.php?id=${properties.id}`;
        name.textContent = properties.title;
        meta.textContent = `${formatBusinessType(properties.businessType)} · ${properties.city}, ${properties.state}`;

        link.append(name, meta);
        searchResults.appendChild(link);
    });

    searchResults.hidden = false;
}

function openBusinessDetails(businessId) {
    window.location.href = `../business_details.php?id=${businessId}`;
}

function getAllEvents(likedOnly = false){
    $.ajax({
        url: likedOnly ? "../mapbox/get_events.php?liked=1" : "../mapbox/get_events.php",
        dataType: "json",
        success: function (eventData) {
            renderEventsFeed(eventData);
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

function renderEventsFeed(events) {
    const feedContainer = document.getElementById('events-feed');

    if (!feedContainer) {
        return;
    }

    if (!events.length) {
        feedContainer.innerHTML = '<p>No upcoming events yet.</p>';
        return;
    }

    feedContainer.innerHTML = events.map((event) => {
        const eventDate = new Date(`${event.date}T${event.startTime}`);
        const endTime = event.endTime ? ` - ${formatEventTime(event.endTime)}` : '';
        const coverPhoto = event.coverPhotoUrl
            ? `<img class="event-feed-cover" src="${event.coverPhotoUrl}" alt="">`
            : '';

        return `
            <article class="event-feed-item ${event.coverPhotoUrl ? 'event-feed-item-with-cover' : ''}">
                ${coverPhoto}
                <div class="event-feed-date">
                    <strong>${eventDate.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}</strong>
                    <span>${eventDate.toLocaleDateString(undefined, { weekday: 'short' })}</span>
                </div>
                <div class="event-feed-details">
                    <h3>${event.name}</h3>
                    <p>${formatEventTime(event.startTime)}${endTime} &middot; ${event.businessName}</p>
                    <p>${formatBusinessType(event.businessType)} &middot; ${event.city}, ${event.state}</p>
                    ${event.description ? `<p>${event.description}</p>` : ''}
                    <a href="../business_details.php?id=${event.businessId}">View business</a>
                </div>
            </article>
        `;
    }).join('');
}

function formatEventTime(time) {
    const date = new Date(`2000-01-01T${time}`);

    return date.toLocaleTimeString(undefined, {
        hour: 'numeric',
        minute: '2-digit'
    });
}

function setupPortalTabs() {
    const tabs = document.querySelectorAll('.portal-tab');
    const panels = document.querySelectorAll('.portal-panel');
    const likedEventsOnly = document.getElementById('liked-events-only');

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const targetPanelId = tab.dataset.tab;

            tabs.forEach((item) => item.classList.remove('is-active'));
            panels.forEach((panel) => panel.classList.add('portal-panel-hidden'));

            tab.classList.add('is-active');
            document.getElementById(targetPanelId).classList.remove('portal-panel-hidden');
        });
    });

    if (likedEventsOnly) {
        likedEventsOnly.addEventListener('change', () => {
            getAllEvents(likedEventsOnly.checked);
        });
    }
}
