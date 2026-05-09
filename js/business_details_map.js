const businessLocationMapContainer = document.getElementById('business-location-map');

if (businessLocationMapContainer && window.mapboxgl && window.MAPBOX_ACCESS_TOKEN) {
    const latitude = Number(businessLocationMapContainer.dataset.businessLatitude);
    const longitude = Number(businessLocationMapContainer.dataset.businessLongitude);
    const businessName = businessLocationMapContainer.dataset.businessName || 'Business';
    const businessType = businessLocationMapContainer.dataset.businessType || 'business';

    if (Number.isFinite(latitude) && Number.isFinite(longitude)) {
        const businessTypeColors = {
            brewery: '#f97316',
            winery: '#9333ea',
            cidery: '#dc2626',
            distillery: '#2563eb',
            distilery: '#2563eb',
            meadery: '#facc15'
        };

        mapboxgl.accessToken = window.MAPBOX_ACCESS_TOKEN;

        const map = new mapboxgl.Map({
            container: businessLocationMapContainer,
            style: 'mapbox://styles/mapbox/standard',
            config: {
                basemap: {
                    showPlaceLabels: true,
                    showPointOfInterestLabels: false,
                    fuelingStationModePointOfInterestLabels: 'none',
                    showIndoorLabels: false
                }
            },
            center: [longitude, latitude],
            zoom: 13.5
        });

        map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');

        map.on('load', () => {
            map.addSource('business-location', {
                type: 'geojson',
                data: {
                    type: 'FeatureCollection',
                    features: [
                        {
                            type: 'Feature',
                            geometry: {
                                type: 'Point',
                                coordinates: [longitude, latitude]
                            },
                            properties: {
                                name: businessName,
                                color: businessTypeColors[businessType] || '#6b7280'
                            }
                        }
                    ]
                }
            });

            map.addLayer({
                id: 'business-location-ring',
                type: 'circle',
                source: 'business-location',
                paint: {
                    'circle-radius': 24,
                    'circle-color': ['get', 'color'],
                    'circle-opacity': 0.18
                }
            });

            map.addLayer({
                id: 'business-location-badge',
                type: 'circle',
                source: 'business-location',
                paint: {
                    'circle-radius': 15,
                    'circle-color': ['get', 'color'],
                    'circle-stroke-width': 3,
                    'circle-stroke-color': '#ffffff'
                }
            });

            map.addLayer({
                id: 'business-location-symbol',
                type: 'symbol',
                source: 'business-location',
                layout: {
                    'text-field': '1',
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
                id: 'business-location-title',
                type: 'symbol',
                source: 'business-location',
                layout: {
                    'text-field': ['get', 'name'],
                    'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
                    'text-size': 14,
                    'text-offset': [0, -1.7],
                    'text-anchor': 'bottom',
                    'text-allow-overlap': true,
                    'text-ignore-placement': true
                },
                paint: {
                    'text-color': ['get', 'color'],
                    'text-halo-color': '#ffffff',
                    'text-halo-width': 1
                }
            });
        });
    }
}
