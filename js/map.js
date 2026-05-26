let allBusinessFeatures = [];
let numberedBusinessFeatures = [];
let userLocation = null;
let locationsAreLoaded = false;
let hasCenteredOnUserLocation = false;
let loadedBusinessCount = 0;
let renderedMapItemCount = 0;
let userLocationPollId = null;
let isUserLocationRequestInFlight = false;
let hasBoundUserLocationLifecycleEvents = false;
let collapsedBusinessListSortValue = null;
const mapRadiusModeMinZoom = 7;
const defaultMapRadiusMiles = 50;
const milesToMeters = 1609.344;
const userLocationRefreshMs = 10000;
const mapClusterMaxZoom = 12;
const fallbackMapCenter = [-98.5795, 39.8283];
const fallbackMapZoom = 3.2;
const initialUserMapZoom = 11.5;
const isMobileMapViewport = window.matchMedia('(max-width: 700px), (pointer: coarse)').matches;
const mapMarkerCircleRadius = isMobileMapViewport ? 20 : 15;
const mapMarkerTextSize = isMobileMapViewport ? 18 : 14;
const mapTitleTextSize = isMobileMapViewport ? 16 : 13;
const mapTitleOffset = isMobileMapViewport ? [0, -1.85] : [0, -1.5];
const mapClusterCircleRadius = isMobileMapViewport ? [22, 26, 30] : [17, 21, 25];
const mapClusterRingRadius = isMobileMapViewport ? [30, 35, 40] : [24, 28, 32];
const mapClusterTextSize = isMobileMapViewport ? 16 : 13;
const mapMarkerHitboxRadius = isMobileMapViewport ? 34 : 24;
const mapClusterHitboxRadius = isMobileMapViewport ? [38, 44, 50] : [28, 34, 40];
const businessTypeFilters = ['brewery', 'winery', 'cidery', 'distillery', 'meadery', 'bar', 'social_club'];

mapboxgl.accessToken = window.MAPBOX_ACCESS_TOKEN;
let map = null;

// Start locating the user immediately so native iOS does not wait for Mapbox
// style loading before beginning the first location lookup.
requestInitialUserLocation();
setupUserLocationLifecycleRefresh();

function createCraftCrawlMap(initialCenter, initialZoom) {
    if (map) {
        return;
    }

    map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/standard',
        config: {
            basemap: {
                showPlaceLabels: true,
                showPointOfInterestLabels: false,
                fuelingStationModePointOfInterestLabels: 'none',
                showIndoorLabels: false
            }
        },
        center: initialCenter,
        zoom: initialZoom
    });

    setupMapLayersAndInteractions();
}

function createFallbackMap() {
    createCraftCrawlMap(fallbackMapCenter, fallbackMapZoom);
}

function setupMapLayersAndInteractions() {
map.on('load', function () {
    updateMapZoomDebug();

    //place object we will add our events to
    map.addSource('places',{
        'type': 'geojson',
        'cluster': true,
        'clusterMaxZoom': mapClusterMaxZoom,
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
        maxzoom: mapClusterMaxZoom + 1,
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
                mapClusterRingRadius[0],
                5, mapClusterRingRadius[1],
                10, mapClusterRingRadius[2]
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
        maxzoom: mapClusterMaxZoom + 1,
        paint: {
            'circle-color': '#475569',
            'circle-radius': [
                'step',
                ['get', 'point_count'],
                mapClusterCircleRadius[0],
                5, mapClusterCircleRadius[1],
                10, mapClusterCircleRadius[2]
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
        maxzoom: mapClusterMaxZoom + 1,
        layout: {
            'text-field': ['concat', ['get', 'point_count_abbreviated'], '+'],
            'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
            'text-size': mapClusterTextSize,
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
        id: 'cluster-hitboxes',
        type: 'circle',
        source: 'places',
        filter: ['has', 'point_count'],
        maxzoom: mapClusterMaxZoom + 1,
        paint: {
            'circle-color': '#ffffff',
            'circle-radius': [
                'step',
                ['get', 'point_count'],
                mapClusterHitboxRadius[0],
                5, mapClusterHitboxRadius[1],
                10, mapClusterHitboxRadius[2]
            ],
            'circle-opacity': 0.01,
            'circle-stroke-opacity': 0
        }
    });

    //add place object to map
    map.addLayer({
        id: 'place-badges',
        type: 'circle',
        source: 'places',
        filter: ['!', ['has', 'point_count']],
        paint: {
            'circle-radius': mapMarkerCircleRadius,
            'circle-color': [
                'match',
                ['get', 'businessType'],
                'brewery', '#f97316',
                'winery', '#9333ea',
                'cidery', '#dc2626',
                'distillery', '#2563eb',
                'meadery', '#facc15',
                'bar', '#0f766e',
                'social_club', '#92400e',
                '#6b7280'
            ],
            'circle-stroke-width': isMobileMapViewport ? 4 : 3,
            'circle-stroke-color': '#ffffff'
        }
    });

    map.addLayer({
        id: 'place-hitboxes',
        type: 'circle',
        source: 'places',
        filter: ['!', ['has', 'point_count']],
        paint: {
            'circle-color': '#ffffff',
            'circle-radius': mapMarkerHitboxRadius,
            'circle-opacity': 0.01,
            'circle-stroke-opacity': 0
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
            'text-size': mapMarkerTextSize,
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
            'text-size': mapTitleTextSize,
            'text-offset': mapTitleOffset,
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
                'bar', '#0f766e',
                'social_club', '#92400e',
                '#6b7280'
            ],
            'text-halo-color': '#ffffff',
            'text-halo-width': 1
        }
    });

    map.addSource('user-location', {
        type: 'geojson',
        data: {
            type: 'FeatureCollection',
            features: []
        }
    });

    map.addLayer({
        id: 'user-location-ring',
        type: 'circle',
        source: 'user-location',
        paint: {
            'circle-radius': 22,
            'circle-color': '#2563eb',
            'circle-opacity': 0.18
        }
    });

    map.addLayer({
        id: 'user-location-dot',
        type: 'circle',
        source: 'user-location',
        paint: {
            'circle-radius': 8,
            'circle-color': '#2563eb',
            'circle-stroke-width': 3,
            'circle-stroke-color': '#ffffff'
        }
    });


    //Handle popups
    const zoomToCluster = (e) => {
        const features = map.queryRenderedFeatures(e.point, {
            layers: ['cluster-hitboxes', 'clusters']
        });

        if (!features.length) {
            return;
        }

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
    };

    const openBusinessPopup = (e) => {
        const coordinates = e.features[0].geometry.coordinates.slice();
        const properties = e.features[0].properties;

        const openPopup = () => {
            new mapboxgl.Popup()
                .setLngLat(coordinates)
                .setHTML(getBusinessPopupHTML(properties, coordinates))
                .addTo(map);
        };

        if (properties.businessType === 'social_club') {
            showSocialClubDisclaimerIfNeeded(openPopup);
        } else {
            openPopup();
        }
    };

    map.on('click', 'clusters', zoomToCluster);
    map.on('click', 'cluster-hitboxes', zoomToCluster);
    map.on('click', 'places', openBusinessPopup);
    map.on('click', 'place-titles', openBusinessPopup);
    map.on('click', 'place-hitboxes', openBusinessPopup);


    //Handle pointer styles
    ['clusters', 'cluster-hitboxes', 'places', 'place-titles', 'place-hitboxes'].forEach((layerId) => {
        map.on('mouseenter', layerId, () => {
            map.getCanvas().style.cursor = 'pointer';
        });
        map.on('mouseleave', layerId, () => {
            map.getCanvas().style.cursor = '';
        });
    });

    //get our data from php function
    getAllLocations();
    updateUserLocationMarker();
    startUserLocationRefresh();
    setupUserLocationControl();
    setupMapExpandControl();

    map.on('moveend', (event) => {
        updateBusinessListForCurrentMapArea(Boolean(event.originalEvent));
    });

    map.on('zoom', () => {
        updateMapZoomDebug();
    });

    map.on('idle', () => {
        updateRenderedMapItemCount();
    });
});
}

setupPortalTabs();

window.addEventListener('craftcrawl:user-tab-changed', (event) => {
    if (event.detail?.tab !== 'map') {
        setMapExpanded(false);
    }

    if (event.detail?.tab === 'map' && map) {
        window.setTimeout(() => map.resize(), 0);
    }
});

function formatBusinessType(type) {
    const labels = {
        brewery: 'Brewery',
        winery: 'Winery',
        cidery: 'Cidery',
        distillery: 'Distillery',
        meadery: 'Meadery',
        bar: 'Bar',
        social_club: 'Social Club'
    };

    return labels[type] || 'Business';
}

function escapeHtml(value) {
    const element = document.createElement('span');
    element.textContent = value || '';
    return element.innerHTML;
}

function showSocialClubDisclaimerIfNeeded(callback) {
    if (window.CRAFTCRAWL_SHOW_SOCIAL_CLUB_DISCLAIMER === false) {
        callback();
        return;
    }

    const storageKey = 'craftcrawl_social_club_disclaimer_seen';

    if (sessionStorage.getItem(storageKey)) {
        callback();
        return;
    }

    const existing = document.querySelector('[data-social-club-disclaimer]');
    if (existing) {
        existing.remove();
        document.body.classList.remove('welcome-modal-open');
    }

    const modal = document.createElement('section');
    modal.className = 'welcome-modal';
    modal.setAttribute('data-social-club-disclaimer', '');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'social-club-disclaimer-title');
    modal.innerHTML = `
        <div class="welcome-modal-backdrop" aria-hidden="true"></div>
        <div class="welcome-modal-panel">
            <span class="welcome-modal-kicker">Heads up</span>
            <h2 id="social-club-disclaimer-title">Membership may be required.</h2>
            <p>Social clubs often require a membership or guest sponsorship for entry. Check with the location directly before visiting.</p>
            <p><small>These popups can be disabled in settings.</small></p>
            <button type="button" data-social-club-disclaimer-dismiss>Got it</button>
        </div>
    `;

    document.body.appendChild(modal);
    document.body.classList.add('welcome-modal-open');
    modal.querySelector('[data-social-club-disclaimer-dismiss]')?.focus();

    modal.querySelector('[data-social-club-disclaimer-dismiss]')?.addEventListener('click', () => {
        sessionStorage.setItem(storageKey, '1');
        modal.classList.add('is-closing');
        document.body.classList.remove('welcome-modal-open');
        window.setTimeout(() => {
            modal.remove();
            callback();
        }, 180);
    });
}

function getBusinessPopupHTML(properties, coordinates) {
    const address = `${properties.streetAddress}, ${properties.city}, ${properties.state} ${properties.zip}`;
    const latitude = coordinates[1];
    const longitude = coordinates[0];
    const directionsUrl = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(`${latitude},${longitude}`)}`;

    return `
        <strong>${escapeHtml(properties.title)}</strong>
        <p>${escapeHtml(formatBusinessType(properties.businessType))} &middot; #${escapeHtml(properties.listNumber)} on map</p>
        <p>
            ${escapeHtml(properties.streetAddress)}<br>
            ${escapeHtml(properties.city)}, ${escapeHtml(properties.state)} ${escapeHtml(properties.zip)}
        </p>
        <div class="map-popup-actions">
            <a class="map-action-button" href="../business_details.php?id=${encodeURIComponent(properties.id)}">Open details</a>
            <a class="map-action-button" href="${directionsUrl}" data-directions-address="${escapeHtml(address)}" data-directions-latitude="${escapeHtml(latitude)}" data-directions-longitude="${escapeHtml(longitude)}" target="_blank" rel="noopener">Get Directions</a>
        </div>
    `;
}

function getAllLocations(){
    $.ajax({
        url: "../mapbox/get_locations.php",
        dataType: "json",
        success: function (businessData) {
            allBusinessFeatures = businessData;
            locationsAreLoaded = true;
            setupBusinessSearch();
            setupBusinessListSort();
            applyLocationAwareListAndMap();
        },
        error: function (e) {
            console.log(e);
        }
    });
}

function requestInitialUserLocation() {
    requestUserLocation({
        enableHighAccuracy: false,
        maximumAge: 300000,
        timeout: 4500,
        requestPreciseAfterCoarse: true
    });
}

function requestUserLocation(options = {}) {
    const locationProvider = window.CraftCrawlLocation || null;
    const shouldShowErrors = options.showErrors !== false;
    const shouldUpdateDependentViews = options.updateDependentViews !== false;
    const locationOptions = {
        enableHighAccuracy: options.enableHighAccuracy !== false,
        timeout: options.timeout || 12000,
        maximumAge: options.maximumAge === undefined ? 60000 : options.maximumAge
    };

    if (!locationProvider && !navigator.geolocation) {
        if (shouldShowErrors) {
            showLocationStatus('Your browser does not support location lookup.', true);
        }
        createFallbackMap();
        return;
    }

    if (isUserLocationRequestInFlight) {
        return;
    }

    isUserLocationRequestInFlight = true;
    let didStartLocationRequest = true;

    if (locationProvider) {
        didStartLocationRequest = locationProvider.getCurrentPosition(handlePosition, handleLocationError, locationOptions);
    } else {
        navigator.geolocation.getCurrentPosition(handlePosition, handleLocationError, locationOptions);
    }

    if (!didStartLocationRequest) {
        isUserLocationRequestInFlight = false;
        if (!map) {
            createFallbackMap();
        }

        if (shouldUpdateDependentViews) {
            updateLocationAwareBusinessList();
        }
    }

    function handlePosition(position) {
        isUserLocationRequestInFlight = false;
        userLocation = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy
        };

        if (!map) {
            createCraftCrawlMap([userLocation.longitude, userLocation.latitude], initialUserMapZoom);
            hasCenteredOnUserLocation = true;
        }

        updateUserLocationMarker();
        if (options.centerOnLocation) {
            centerMapOnUserLocation();
            hasCenteredOnUserLocation = true;
        }

        if (shouldUpdateDependentViews) {
            applyLocationAwareListAndMap(options);
        }

        if (options.requestPreciseAfterCoarse) {
            requestPreciseUserLocationAfterCoarse(false);
        }
    }

    function handleLocationError(error) {
        isUserLocationRequestInFlight = false;
        const willRequestPreciseLocation = options.requestPreciseAfterCoarse && (!error || error.code !== 1);
        if (!map && !willRequestPreciseLocation) {
            createFallbackMap();
        }

        if (shouldShowErrors && error && error.code === 1) {
            showLocationStatus('Location access is off. Enable location access for CraftCrawl to center the map near you.', true);
        }

        if (shouldUpdateDependentViews) {
            updateLocationAwareBusinessList();
        }

        if (willRequestPreciseLocation) {
            requestPreciseUserLocationAfterCoarse(shouldShowErrors);
        }
    }

    function requestPreciseUserLocationAfterCoarse(showErrors) {
        window.setTimeout(() => {
            requestUserLocation({
                allowInitialCenter: false,
                allowSortSwitch: false,
                enableHighAccuracy: true,
                maximumAge: 0,
                showErrors,
                updateDependentViews: true
            });
        }, 0);
    }
}

function startUserLocationRefresh() {
    if (userLocationPollId) {
        return;
    }

    userLocationPollId = window.setInterval(() => {
        if (document.hidden) {
            return;
        }

        requestUserLocation({
            allowInitialCenter: false,
            allowSortSwitch: false,
            maximumAge: 5000,
            showErrors: false,
            updateDependentViews: false
        });
    }, userLocationRefreshMs);
}

function setupUserLocationLifecycleRefresh() {
    if (hasBoundUserLocationLifecycleEvents) {
        return;
    }

    hasBoundUserLocationLifecycleEvents = true;

    const refreshVisibleUserLocation = () => {
        if (document.hidden) {
            return;
        }

        requestUserLocation({
            allowInitialCenter: false,
            allowSortSwitch: false,
            maximumAge: 15000,
            showErrors: false,
            updateDependentViews: !userLocation
        });
    };

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshVisibleUserLocation();
        }
    });

    window.addEventListener('pageshow', refreshVisibleUserLocation);
}

function setupUserLocationControl() {
    const button = document.getElementById('map-center-user');

    if (!button || button.dataset.ready === 'true') {
        return;
    }

    button.dataset.ready = 'true';
    button.addEventListener('click', () => {
        if (userLocation) {
            centerMapOnUserLocation();
        }

        requestUserLocation({
            centerOnLocation: true,
            allowInitialCenter: false,
            maximumAge: 0,
            showErrors: true
        });
    });
}

function setupMapExpandControl() {
    const button = document.getElementById('map-expand-toggle');

    if (!button || button.dataset.ready === 'true') {
        return;
    }

    button.dataset.ready = 'true';
    button.addEventListener('click', () => {
        const mapPanel = document.getElementById('map-panel');
        setMapExpanded(!mapPanel?.classList.contains('is-map-expanded'));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setMapExpanded(false);
        }
    });
}

function setMapExpanded(isExpanded) {
    const mapPanel = document.getElementById('map-panel');
    const button = document.getElementById('map-expand-toggle');
    const sortSelect = document.getElementById('business-list-sort');
    const sortLabel = document.querySelector('label[for="business-list-sort"]');

    if (!mapPanel || !button) {
        return;
    }

    if (isExpanded && !mapPanel.classList.contains('is-map-expanded')) {
        collapsedBusinessListSortValue = sortSelect ? sortSelect.value : null;
    }

    mapPanel.classList.toggle('is-map-expanded', isExpanded);
    document.body.classList.toggle('map-expanded-open', isExpanded);
    button.setAttribute('aria-pressed', isExpanded ? 'true' : 'false');
    button.setAttribute('aria-label', isExpanded ? 'Collapse map' : 'Expand map');
    button.title = isExpanded ? 'Collapse map' : 'Expand map';
    if (sortLabel) {
        sortLabel.textContent = isExpanded ? 'Sort map' : 'Sort list';
    }
    updateExpandedSortOptions(isExpanded);

    if (sortSelect) {
        if (isExpanded && sortSelect.value !== 'all_types') {
            sortSelect.value = 'all_types';
        } else if (!isExpanded && collapsedBusinessListSortValue) {
            sortSelect.value = collapsedBusinessListSortValue;
        }
    }

    if (locationsAreLoaded) {
        if (isExpanded) {
            updateExpandedMapForSort();
        } else {
            updateLocationAwareBusinessList();
        }
    }

    window.setTimeout(() => {
        map?.resize();
        updateBusinessListRadiusThumb({ animate: false });
    }, 0);
}

function isMapExpanded() {
    return Boolean(document.getElementById('map-panel')?.classList.contains('is-map-expanded'));
}

function updateExpandedSortOptions(isExpanded) {
    const sortSelect = document.getElementById('business-list-sort');

    if (!sortSelect) {
        return;
    }

    Array.from(sortSelect.options).forEach((option) => {
        const isListOnlySort = option.value === 'map' || option.value === 'nearby' || option.value === 'name';
        const isExpandedOnlySort = option.value === 'all_types';
        option.hidden = (isExpanded && isListOnlySort) || (!isExpanded && isExpandedOnlySort);
        option.disabled = (isExpanded && isListOnlySort) || (!isExpanded && isExpandedOnlySort);
    });
}

function applyLocationAwareListAndMap(options = {}) {
    if (!locationsAreLoaded) {
        return;
    }

    const sortSelect = document.getElementById('business-list-sort');
    const allowSortSwitch = options.allowSortSwitch !== false;
    const allowInitialCenter = options.allowInitialCenter !== false;

    if (userLocation && sortSelect && allowSortSwitch && (sortSelect.value === 'map' || sortSelect.value === 'nearby')) {
        sortSelect.value = 'nearby';
        updateBusinessListForSort('nearby');

        if (allowInitialCenter && !hasCenteredOnUserLocation) {
            hasCenteredOnUserLocation = true;
            centerMapOnUserLocation();
        }

        return;
    }

    updateLocationAwareBusinessList();
}

function updateLocationAwareBusinessList() {
    if (!locationsAreLoaded) {
        return;
    }

    const sortSelect = document.getElementById('business-list-sort');
    updateBusinessListForSort(sortSelect ? sortSelect.value : 'map');
}

function showLocationStatus(message, isError) {
    const status = document.querySelector('[data-checkin-status]');

    if (!status) {
        return;
    }

    status.textContent = message;
    status.classList.toggle('form-message-error', isError);
    status.classList.toggle('form-message-success', !isError);
    status.hidden = false;
}

function updateUserLocationMarker() {
    if (!map) {
        return;
    }

    const source = map.getSource('user-location');

    if (!source || !userLocation) {
        return;
    }

    source.setData({
        type: 'FeatureCollection',
        features: [
            {
                type: 'Feature',
                geometry: {
                    type: 'Point',
                    coordinates: [userLocation.longitude, userLocation.latitude]
                },
                properties: {}
            }
        ]
    });
}

function centerMapOnUserLocation() {
    if (!map || !userLocation) {
        return;
    }

    map.easeTo({
        center: [userLocation.longitude, userLocation.latitude],
        zoom: Math.max(map.getZoom(), 11.5),
        bearing: 0,
        pitch: 0,
        duration: 900
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
            const match = matches[0];
            if (match.properties.businessType === 'social_club') {
                showSocialClubDisclaimerIfNeeded(() => openBusinessDetails(match.properties.id));
            } else {
                openBusinessDetails(match.properties.id);
            }
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

    const terms = normalizedQuery
        .split(/[^a-z0-9]+/i)
        .filter((term) => term.length >= 2);

    return allBusinessFeatures.map((feature) => {
        const properties = feature.properties;
        const searchableText = [
            properties.title,
            formatBusinessType(properties.businessType),
            properties.streetAddress,
            properties.city,
            properties.state,
            properties.zip
        ].join(' ').toLowerCase();

        if (searchableText.includes(normalizedQuery)) {
            return { feature, score: 10 };
        }

        const matchedTerms = terms.filter((term) => searchableText.includes(term)).length;
        return { feature, score: matchedTerms };
    })
        .filter((match) => match.score > 0)
        .sort((a, b) => b.score - a.score || String(a.feature.properties.title).localeCompare(String(b.feature.properties.title)))
        .map((match) => match.feature)
        .slice(0, 6);
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

        if (properties.businessType === 'social_club') {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                searchResults.hidden = true;
                showSocialClubDisclaimerIfNeeded(() => openBusinessDetails(properties.id));
            });
        }

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

    if (!features.length) {
        listContainer.innerHTML = '<li class="business-list-empty">No businesses found in this map area.</li>';
        return;
    }

    listContainer.innerHTML = features.map((feature) => {
        const properties = feature.properties;
        const distanceText = userLocation
            ? `${formatDistance(distanceMeters(userLocation.latitude, userLocation.longitude, feature.geometry.coordinates[1], feature.geometry.coordinates[0]))} away`
            : '';

        return `
            <li class="business-list-item" data-business-id="${escapeHtml(properties.id)}" data-business-type="${escapeHtml(properties.businessType)}" role="button" tabindex="0" aria-label="Show ${escapeHtml(properties.title)} on map">
                <span class="business-list-number business-list-number-${properties.businessType}">
                    ${properties.listNumber}
                </span>
                <div class="business-list-details">
                    <strong>${escapeHtml(properties.title)}</strong>
                    <span class="business-list-location">${formatBusinessType(properties.businessType)} &middot; ${escapeHtml(properties.city)}, ${escapeHtml(properties.state)}</span>
                    ${distanceText ? `<span class="business-list-proximity">${escapeHtml(distanceText)}</span>` : ''}
                </div>
                <a href="../business_details.php?id=${properties.id}">Open details</a>
            </li>
        `;
    }).join('');

    setupBusinessListMapLinks(listContainer);
}

function setupBusinessListMapLinks(listContainer) {
    listContainer.querySelectorAll('.business-list-item').forEach((item) => {
        item.addEventListener('click', (event) => {
            const clickedLink = event.target instanceof Element ? event.target.closest('a') : null;

            if (clickedLink) {
                if (item.dataset.businessType === 'social_club') {
                    event.preventDefault();
                    showSocialClubDisclaimerIfNeeded(() => {
                        window.location.href = clickedLink.href;
                    });
                }
                return;
            }

            item.blur();

            if (item.dataset.businessType === 'social_club') {
                showSocialClubDisclaimerIfNeeded(() => focusBusinessOnMap(item.dataset.businessId));
            } else {
                focusBusinessOnMap(item.dataset.businessId);
            }
        });

        item.addEventListener('keydown', (event) => {
            const focusedLink = event.target instanceof Element ? event.target.closest('a') : null;

            if (focusedLink || (event.key !== 'Enter' && event.key !== ' ')) {
                return;
            }

            event.preventDefault();

            if (item.dataset.businessType === 'social_club') {
                showSocialClubDisclaimerIfNeeded(() => focusBusinessOnMap(item.dataset.businessId));
            } else {
                focusBusinessOnMap(item.dataset.businessId);
            }
        });
    });
}

function focusBusinessOnMap(businessId) {
    const feature = numberedBusinessFeatures.find((businessFeature) => getBusinessFeatureId(businessFeature) === String(businessId))
        || allBusinessFeatures.find((businessFeature) => getBusinessFeatureId(businessFeature) === String(businessId));
    const mapContainer = document.getElementById('map');

    if (!feature) {
        return;
    }

    map.easeTo({
        center: feature.geometry.coordinates,
        zoom: Math.max(map.getZoom(), 13),
        duration: 650
    });

    if (mapContainer) {
        mapContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    new mapboxgl.Popup()
        .setLngLat(feature.geometry.coordinates)
        .setHTML(getBusinessPopupHTML(feature.properties, feature.geometry.coordinates))
        .addTo(map);
}

function setupBusinessListSort() {
    const sortSelect = document.getElementById('business-list-sort');
    const radiusInputs = document.querySelectorAll('input[name="business-list-radius"]');
    updateBusinessListRadiusThumb({ animate: false });

    if (!window.CraftCrawlRadiusThumbResizeReady) {
        window.CraftCrawlRadiusThumbResizeReady = true;
        window.addEventListener('resize', () => updateBusinessListRadiusThumb({ animate: false }));
    }

    if (sortSelect && sortSelect.dataset.ready !== 'true') {
        sortSelect.dataset.ready = 'true';
        sortSelect.addEventListener('change', () => {
            if (isMapExpanded()) {
                updateExpandedMapForSort();
                return;
            }

            if (sortSelect.value === 'nearby' && !userLocation) {
                requestUserLocation();
                return;
            }

            updateBusinessListForSort(sortSelect.value);
        });
    }

    radiusInputs.forEach((radiusInput) => {
        if (radiusInput.dataset.ready === 'true') {
            return;
        }

        radiusInput.dataset.ready = 'true';
        radiusInput.addEventListener('change', () => {
            updateBusinessListRadiusThumb();

            if (isMapExpanded()) {
                updateExpandedMapForSort();
                return;
            }

            updateLocationAwareBusinessList();
        });
    });
}

function updateBusinessListRadiusThumb(options = {}) {
    const shouldAnimate = options.animate !== false;
    const toggle = document.querySelector('.business-list-radius-toggle');
    const checkedInput = toggle?.querySelector('input[name="business-list-radius"]:checked');
    const checkedLabel = checkedInput ? checkedInput.nextElementSibling : null;

    if (!toggle || !(checkedLabel instanceof HTMLLabelElement)) {
        if (toggle) {
            toggle.style.setProperty('--radius-toggle-thumb-opacity', '0');
            toggle.classList.remove('is-radius-thumb-ready');
        }
        return;
    }

    const toggleRect = toggle.getBoundingClientRect();
    const labelRect = checkedLabel.getBoundingClientRect();

    if (!toggleRect.width || !labelRect.width) {
        window.requestAnimationFrame(() => updateBusinessListRadiusThumb(options));
        return;
    }

    if (!shouldAnimate) {
        toggle.classList.remove('is-radius-thumb-ready');
    }

    toggle.style.setProperty('--radius-toggle-thumb-left', `${labelRect.left - toggleRect.left}px`);
    toggle.style.setProperty('--radius-toggle-thumb-top', `${labelRect.top - toggleRect.top}px`);
    toggle.style.setProperty('--radius-toggle-thumb-width', `${labelRect.width}px`);
    toggle.style.setProperty('--radius-toggle-thumb-height', `${labelRect.height}px`);
    toggle.style.setProperty('--radius-toggle-thumb-opacity', '1');

    if (shouldAnimate) {
        toggle.classList.add('is-radius-thumb-ready');
    } else {
        window.requestAnimationFrame(() => {
            toggle.classList.add('is-radius-thumb-ready');
        });
    }
}

function updateBusinessListForCurrentMapArea(useMapCenter = false) {
    if (!locationsAreLoaded) {
        return;
    }

    if (isMapExpanded()) {
        updateExpandedMapForSort();
        return;
    }

    const sortSelect = document.getElementById('business-list-sort');
    const sortValue = sortSelect ? sortSelect.value : 'map';

    if (sortValue !== 'map' && sortValue !== 'nearby') {
        return;
    }

    updateBusinessListForSort(sortValue, { useMapCenter: sortValue === 'map' && useMapCenter });
}

function updateMapZoomDebug() {
    const zoomDebug = document.getElementById('map-zoom-debug');

    if (!zoomDebug || !map) {
        return;
    }

    const zoom = map.getZoom();
    const mode = zoom >= mapRadiusModeMinZoom ? `${getSelectedMapRadiusMiles()} mi radius` : 'map view';
    zoomDebug.textContent = `Zoom ${zoom.toFixed(2)} · ${mode} · ${loadedBusinessCount} businesses · ${renderedMapItemCount} items`;
}

function updateBusinessListForSort(sortValue, options = {}) {
    const reference = getListReferencePoint(sortValue, options.useMapCenter);
    const features = sortValue === 'map' || sortValue === 'nearby'
        ? getMapRelevantBusinessFeatures()
        : getSortedBusinessFeatures(sortValue);
    const orderedFeatures = sortFeaturesForList(features, sortValue, reference);
    const numberedFeatures = numberFeatures(orderedFeatures);

    updateMapBusinessNumbers(numberedFeatures);
    renderBusinessList(numberedFeatures);
}

function updateExpandedMapForSort() {
    if (!locationsAreLoaded) {
        return;
    }

    const sortSelect = document.getElementById('business-list-sort');
    const sortValue = sortSelect ? sortSelect.value : 'all_types';
    const features = getMapRelevantBusinessFeatures();
    const orderedFeatures = sortFeaturesForExpandedMap(features, sortValue);

    updateMapBusinessNumbers(orderedFeatures);
}

function getSortedBusinessFeatures(sortValue) {
    const features = [...allBusinessFeatures];

    if (sortValue === 'name') {
        return features.sort(compareBusinessTitles);
    }

    if (sortValue === 'nearby' && userLocation) {
        return features.sort((a, b) => {
            const distanceA = distanceMeters(userLocation.latitude, userLocation.longitude, a.geometry.coordinates[1], a.geometry.coordinates[0]);
            const distanceB = distanceMeters(userLocation.latitude, userLocation.longitude, b.geometry.coordinates[1], b.geometry.coordinates[0]);

            return distanceA - distanceB;
        });
    }

    if (isBusinessTypeFilter(sortValue)) {
        return features
            .filter((feature) => feature.properties.businessType === sortValue)
            .sort(compareBusinessTitles);
    }

    return features.sort(compareBusinessTitles);
}

function sortFeaturesForExpandedMap(features, sortValue) {
    if (sortValue === 'all_types') {
        const reference = getMapCenterReference();

        return [...features].sort((a, b) => {
            return distanceFromFeatureToReference(a, reference) - distanceFromFeatureToReference(b, reference);
        });
    }

    if (isBusinessTypeFilter(sortValue)) {
        return [...features]
            .filter((feature) => feature.properties.businessType === sortValue)
            .sort(compareBusinessTitles);
    }

    return [];
}

function sortFeaturesForList(features, sortValue, reference) {
    const sortedFeatures = [...features];

    if ((sortValue === 'map' || sortValue === 'nearby') && reference) {
        return sortedFeatures.sort((a, b) => {
            return distanceFromFeatureToReference(a, reference) - distanceFromFeatureToReference(b, reference);
        });
    }

    if (sortValue === 'map' || sortValue === 'nearby') {
        return sortedFeatures.sort((a, b) => a.properties.title.localeCompare(b.properties.title));
    }

    if (sortValue === 'name') {
        return sortedFeatures.sort(compareBusinessTitles);
    }

    if (isBusinessTypeFilter(sortValue)) {
        return sortedFeatures
            .filter((feature) => feature.properties.businessType === sortValue)
            .sort(compareBusinessTitles);
    }

    return sortedFeatures.sort(compareBusinessTitles);
}

function getMapRelevantBusinessFeatures() {
    const center = getMapCenterReference();
    if (map.getZoom() >= mapRadiusModeMinZoom) {
        return sortFeaturesByReference(getBusinessFeaturesWithinMapRadius(), center);
    }

    return sortFeaturesByReference(getVisibleBusinessFeatures(), center);
}

function isBusinessTypeFilter(sortValue) {
    return businessTypeFilters.includes(sortValue);
}

function updateRenderedMapItemCount() {
    if (!map.getLayer('clusters') || !map.getLayer('places')) {
        return;
    }

    const renderedFeatures = map.queryRenderedFeatures({
        layers: ['clusters', 'places']
    });
    const renderedItems = new Set();

    renderedFeatures.forEach((feature) => {
        if (feature.properties && feature.properties.cluster_id !== undefined) {
            renderedItems.add(`cluster-${feature.properties.cluster_id}`);
            return;
        }

        if (feature.properties && feature.properties.id !== undefined) {
            renderedItems.add(`business-${feature.properties.id}`);
        }
    });

    renderedMapItemCount = renderedItems.size;
    updateMapZoomDebug();
}

function getBusinessFeaturesWithinMapRadius() {
    const reference = getMapCenterReference();
    const mapRadiusMeters = getSelectedMapRadiusMiles() * milesToMeters;

    return allBusinessFeatures.filter((feature) => {
        return distanceFromFeatureToReference(feature, reference) <= mapRadiusMeters;
    });
}

function getSelectedMapRadiusMiles() {
    const radiusInput = document.querySelector('input[name="business-list-radius"]:checked');
    const radiusMiles = Number(radiusInput ? radiusInput.value : defaultMapRadiusMiles);

    return [50, 25, 10, 5].includes(radiusMiles) ? radiusMiles : defaultMapRadiusMiles;
}

function getVisibleBusinessFeatures() {
    const bounds = map.getBounds();

    return allBusinessFeatures.filter((feature) => {
        return bounds.contains(feature.geometry.coordinates);
    });
}

function getListReferencePoint(sortValue, useMapCenter = false) {
    if (!useMapCenter && sortValue === 'nearby' && userLocation) {
        return userLocation;
    }

    return getMapCenterReference();
}

function getMapCenterReference() {
    const center = map.getCenter();

    return {
        latitude: center.lat,
        longitude: center.lng
    };
}

function sortFeaturesByReference(features, reference) {
    return [...features].sort((a, b) => {
        return distanceFromFeatureToReference(a, reference) - distanceFromFeatureToReference(b, reference);
    });
}

function updateMapBusinessNumbers(orderedFeatures) {
    const source = map.getSource('places');

    if (!source) {
        return;
    }

    numberedBusinessFeatures = numberFeatures(orderedFeatures);
    loadedBusinessCount = numberedBusinessFeatures.length;
    updateMapZoomDebug();

    source.setData({
        type: 'FeatureCollection',
        features: numberedBusinessFeatures
    });
}

function numberFeatures(features) {
    return features.map((feature, index) => ({
        ...feature,
        properties: {
            ...feature.properties,
            listNumber: String(index + 1)
        }
    }));
}

function getBusinessFeatureId(feature) {
    return String(feature.properties.id);
}

function distanceFromFeatureToReference(feature, reference) {
    return distanceMeters(
        reference.latitude,
        reference.longitude,
        feature.geometry.coordinates[1],
        feature.geometry.coordinates[0]
    );
}

function compareBusinessTitles(a, b) {
    return a.properties.title.localeCompare(b.properties.title);
}

function distanceMeters(lat1, lng1, lat2, lng2) {
    const earthRadiusMeters = 6371000;
    const latDelta = toRadians(lat2 - lat1);
    const lngDelta = toRadians(lng2 - lng1);
    const a = Math.sin(latDelta / 2) * Math.sin(latDelta / 2)
        + Math.cos(toRadians(lat1)) * Math.cos(toRadians(lat2))
        * Math.sin(lngDelta / 2) * Math.sin(lngDelta / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return earthRadiusMeters * c;
}

function toRadians(degrees) {
    return degrees * Math.PI / 180;
}

function formatDistance(meters) {
    if (meters < 160) {
        return `${Math.round(meters)} m`;
    }

    return `${(meters / 1609.344).toFixed(1)} mi`;
}

function renderEventsFeed(events) {
    const feedContainer = document.getElementById('events-feed');

    if (!feedContainer) {
        return;
    }

    if (!events.length) {
        removeEventStickyDayHeader();
        feedContainer.innerHTML = '<p>No upcoming events yet.</p>';
        return;
    }

    let currentDateKey = '';

    const eventsMarkup = events.map((event) => {
        const eventDate = new Date(`${event.date}T${event.startTime}`);
        const dayLabel = formatEventDayHeader(eventDate);
        const endTime = event.endTime ? ` - ${formatEventTime(event.endTime)}` : '';
        const eventUrl = `../event_details.php?id=${encodeURIComponent(event.id)}&date=${encodeURIComponent(event.date)}`;
        const commentsUrl = `feed_post.php?item=${encodeURIComponent(event.itemKey || `event:${event.id}:${event.date}`)}`;
        const eventName = escapeHtml(event.name);
        const businessName = escapeHtml(event.businessName);
        const city = escapeHtml(event.city);
        const state = escapeHtml(event.state);
        const commentCount = Number(event.commentCount || 0);
        const commentLabel = commentCount > 0 ? `${commentCount} ${commentCount === 1 ? 'Comment' : 'Comments'}` : 'Comments';
        const description = event.description ? escapeHtml(event.description) : '';
        const coverPhoto = event.coverPhotoUrl
            ? `<img class="event-feed-cover" src="${event.coverPhotoUrl}" alt="">`
            : '';
        const dayHeader = event.date !== currentDateKey
            ? `<div class="event-feed-day-header" data-event-day-header data-date-key="${escapeHtml(event.date)}" data-date-label="${escapeHtml(dayLabel)}"><span>${escapeHtml(dayLabel)}</span></div>`
            : '';
        currentDateKey = event.date;

        return `
            ${dayHeader}
            <article class="event-feed-item ${event.coverPhotoUrl ? 'event-feed-item-with-cover' : ''}">
                ${coverPhoto}
                <div class="event-feed-details">
                    <h3>${eventName}</h3>
                    <p>${formatEventTime(event.startTime)}${endTime} &middot; ${businessName}</p>
                    <p>${formatBusinessType(event.businessType)} &middot; ${city}, ${state}</p>
                    ${description ? `<p>${description}</p>` : ''}
                </div>
                <div class="event-feed-actions">
                    <a href="${eventUrl}">View event</a>
                    <a href="../business_details.php?id=${event.businessId}">View business</a>
                    <a class="event-comments-link" href="${commentsUrl}">${commentLabel}</a>
                    <button
                        type="button"
                        class="event-want-button ${event.isWantToGo ? 'is-active' : ''}"
                        data-event-want
                        data-event-id="${event.id}"
                        data-occurrence-date="${escapeHtml(event.date)}"
                        data-is-saved="${event.isWantToGo ? '1' : '0'}"
                    >
                        📍 Want to Go ${Number(event.wantToGoCount || 0)}
                    </button>
                </div>
            </article>
        `;
    }).join('');

    feedContainer.innerHTML = eventsMarkup;
    setupEventStickyDayHeader(feedContainer);

    feedContainer.querySelectorAll('[data-event-want]').forEach((button) => {
        button.addEventListener('click', () => {
            const formData = new FormData();
            formData.append('csrf_token', window.CRAFTCRAWL_CSRF_TOKEN || '');
            formData.append('event_id', button.dataset.eventId);
            formData.append('occurrence_date', button.dataset.occurrenceDate);
            formData.append('is_saved', button.dataset.isSaved || '0');
            button.disabled = true;
            button.classList.add('is-loading');

            fetch('../user/event_want_to_go_toggle.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then((response) => response.json())
                .then((data) => {
                    if (!data.ok) {
                        return;
                    }

                    button.dataset.isSaved = data.is_saved ? '1' : '0';
                    button.classList.toggle('is-active', Boolean(data.is_saved));
                    button.textContent = `📍 Want to Go ${Number(data.count || 0)}`;
                    if (data.xp_reward && window.craftcrawlShowXpReward) {
                        window.craftcrawlShowXpReward(data.xp_reward);
                    }
                    window.dispatchEvent(new CustomEvent('craftcrawl:event-want-updated'));
                })
                .finally(() => {
                    button.disabled = false;
                    button.classList.remove('is-loading');
                });
        });
    });
}

function setupEventStickyDayHeader(container) {
    removeEventStickyDayHeader();

    const stickyHeader = document.createElement('div');
    stickyHeader.className = 'event-feed-floating-day-header';
    stickyHeader.setAttribute('aria-hidden', 'true');
    stickyHeader.innerHTML = '<span></span>';
    document.body.appendChild(stickyHeader);

    const label = stickyHeader.querySelector('span');
    const state = container.eventStickyDayState || {
        activeDate: '',
        ticking: false
    };
    state.header = stickyHeader;
    state.label = label;
    state.activeDate = '';
    container.eventStickyDayState = state;

    function updateStickyHeader() {
        state.ticking = false;
        const headers = Array.from(container.querySelectorAll('[data-event-day-header]'));
        if (!headers.length || !state.header || !state.label) {
            return;
        }

        const feedRect = container.getBoundingClientRect();
        const shouldShow = window.matchMedia('(max-width: 760px)').matches
            && feedRect.top <= 68
            && feedRect.bottom > 96;
        state.header.classList.toggle('is-visible', shouldShow);

        const threshold = 74;
        let activeHeader = headers[0];
        headers.forEach((header) => {
            if (header.getBoundingClientRect().top <= threshold) {
                activeHeader = header;
            }
        });

        const nextDate = activeHeader.dataset.dateKey || '';
        if (nextDate && nextDate !== state.activeDate) {
            state.activeDate = nextDate;
            state.label.textContent = activeHeader.dataset.dateLabel || '';
            state.header.classList.remove('is-changing');
            window.requestAnimationFrame(() => state.header?.classList.add('is-changing'));
        }
    }

    function requestUpdate() {
        if (state.ticking) {
            return;
        }
        state.ticking = true;
        window.requestAnimationFrame(updateStickyHeader);
    }

    if (container.dataset.eventStickyDayReady !== 'true') {
        container.dataset.eventStickyDayReady = 'true';
        window.addEventListener('scroll', requestUpdate, { passive: true });
        window.addEventListener('resize', requestUpdate);
        document.addEventListener('scroll', requestUpdate, { capture: true, passive: true });
    }

    updateStickyHeader();
    window.setTimeout(updateStickyHeader, 80);
}

function removeEventStickyDayHeader() {
    document.querySelectorAll('.event-feed-floating-day-header').forEach((header) => header.remove());
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatEventDayHeader(date) {
    const monthDay = date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    const weekday = date.toLocaleDateString(undefined, { weekday: 'short' });
    return `${monthDay}, ${weekday}`;
}

function formatEventTime(time) {
    const date = new Date(`2000-01-01T${time}`);

    return date.toLocaleTimeString(undefined, {
        hour: 'numeric',
        minute: '2-digit'
    });
}

function setupPortalTabs() {
    const tabs = document.querySelectorAll('.portal-tab[data-tab]');
    const appTabs = document.querySelectorAll('.mobile-app-tab[data-app-tab]');
    const appScrollTabs = document.querySelectorAll('.mobile-app-tab[data-app-scroll-target]');
    const panels = document.querySelectorAll('.portal-panel');
    const likedEventsOnly = document.getElementById('liked-events-only');

    function showPanel(targetPanelId) {
        tabs.forEach((item) => item.classList.toggle('is-active', item.dataset.tab === targetPanelId));
        appTabs.forEach((item) => item.classList.toggle('is-active', item.dataset.appTab === targetPanelId));
        panels.forEach((panel) => panel.classList.toggle('portal-panel-hidden', panel.id !== targetPanelId));
    }

    function scrollPanelIntoView(targetPanelId) {
        const targetPanel = document.getElementById(targetPanelId);
        const portalTabs = document.querySelector('.portal-tabs');
        const target = targetPanel || portalTabs;

        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            showPanel(tab.dataset.tab);
        });
    });

    appTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            showPanel(tab.dataset.appTab);
            scrollPanelIntoView(tab.dataset.appTab);
        });
    });

    appScrollTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const target = document.getElementById(tab.dataset.appScrollTarget);

            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    if (likedEventsOnly) {
        likedEventsOnly.addEventListener('change', () => {
            getAllEvents(likedEventsOnly.checked);
        });
    }
}
