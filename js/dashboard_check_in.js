(function () {
    const widget = document.querySelector('[data-dashboard-checkin]');

    if (!widget) {
        return;
    }

    const findButton = widget.querySelector('[data-find-checkins]');
    const list = widget.querySelector('[data-checkin-list]');
    const feedback = widget.querySelector('[data-checkin-status]');
    const csrfToken = widget.dataset.csrfToken || '';
    let currentPosition = null;

    function showStatus(message, isError) {
        feedback.textContent = message;
        feedback.classList.toggle('form-message-error', isError);
        feedback.classList.toggle('form-message-success', !isError);
        feedback.hidden = false;
    }

    function locationErrorMessage(error) {
        if (!window.isSecureContext) {
            return 'Location check-ins require HTTPS on mobile browsers. Use localhost on this device, an HTTPS tunnel, or serve this dev site over HTTPS.';
        }

        if (error && error.code === error.PERMISSION_DENIED) {
            return 'Location permission was denied. Enable location access for this site in your browser settings and try again.';
        }

        if (error && error.code === error.POSITION_UNAVAILABLE) {
            return 'Your current location is unavailable. Check device location services and try again.';
        }

        if (error && error.code === error.TIMEOUT) {
            return 'Finding your location timed out. Move somewhere with a clearer signal and try again.';
        }

        return 'Location permission is required to find nearby check-ins.';
    }

    function formatBusinessType(type) {
        const labels = {
            brewery: 'Brewery',
            winery: 'Winery',
            cidery: 'Cidery',
            distillery: 'Distillery',
            distilery: 'Distillery',
            meadery: 'Meadery'
        };

        return labels[type] || 'Business';
    }

    function formatDistance(meters) {
        if (meters < 160) {
            return `${meters} m`;
        }

        return `${(meters / 1609.344).toFixed(2)} mi`;
    }

    function postForm(url, values) {
        const formData = new FormData();

        Object.keys(values).forEach((key) => {
            formData.append(key, values[key]);
        });

        return fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then((response) => response.json());
    }

    function renderLocations(locations) {
        list.innerHTML = '';

        if (!locations.length) {
            list.hidden = true;
            showStatus('No check-in locations found nearby. Move closer to a listed location and try again.', true);
            return;
        }

        locations.forEach((location) => {
            const item = document.createElement('article');
            const details = document.createElement('div');
            const title = document.createElement('strong');
            const meta = document.createElement('span');
            const action = document.createElement('button');

            item.className = 'dashboard-checkin-item';
            title.textContent = location.name;

            const visitText = location.visit_type === 'first_time'
                ? `First-time check-in · +${location.xp_awarded} XP`
                : `Repeat check-in · +${location.xp_awarded} XP`;

            meta.textContent = `${formatBusinessType(location.type)} · ${location.city}, ${location.state} · ${formatDistance(location.distance_meters)} · ${location.eligible ? visitText : location.unavailable_reason}`;
            action.type = 'button';
            action.textContent = location.eligible ? 'Check In' : (location.is_open ? 'On Cooldown' : 'Closed');
            action.disabled = !location.eligible;

            action.addEventListener('click', () => {
                action.disabled = true;
                action.classList.add('is-loading');
                action.textContent = 'Checking in...';

                postForm('../check_in.php', {
                    csrf_token: csrfToken,
                    business_id: location.id,
                    latitude: currentPosition.latitude,
                    longitude: currentPosition.longitude
                })
                    .then((data) => {
                        if (!data.ok) {
                            showStatus(data.message || 'Check-in failed.', true);
                            action.disabled = false;
                            action.classList.remove('is-loading');
                            action.textContent = 'Check In';
                            return;
                        }

                        const badgeText = data.badges && data.badges.length
                            ? ` Badges earned: ${data.badges.join(', ')}.`
                            : '';

                        showStatus(`${data.message}${badgeText}`, false);
                        if (window.craftcrawlShowXpReward) {
                            window.craftcrawlShowXpReward(data);
                        }
                        action.textContent = 'Checked In';
                        action.classList.remove('is-loading');
                    })
                    .catch(() => {
                        showStatus('Check-in failed. Please try again.', true);
                        action.disabled = false;
                        action.classList.remove('is-loading');
                        action.textContent = 'Check In';
                    });
            });

            details.append(title, meta);
            item.append(details, action);
            list.appendChild(item);
        });

        list.hidden = false;
    }

    findButton.addEventListener('click', () => {
        const locationProvider = window.CraftCrawlLocation || null;

        if (!locationProvider && !navigator.geolocation) {
            showStatus('Your browser does not support location check-ins.', true);
            return;
        }

        findButton.disabled = true;
        findButton.classList.add('is-loading');
        findButton.textContent = 'Finding nearby...';
        list.hidden = true;

        let didStartLocationRequest = true;

        if (locationProvider) {
            didStartLocationRequest = locationProvider.getCurrentPosition(handlePosition, handleLocationError, {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 0
            });
        } else {
            navigator.geolocation.getCurrentPosition(handlePosition, handleLocationError, {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 0
            });
        }

        if (!didStartLocationRequest) {
            showStatus('Your browser does not support location check-ins.', true);
            findButton.disabled = false;
            findButton.textContent = 'Find Nearby Check-ins';
        }

        function handlePosition(position) {
            currentPosition = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude
            };

            postForm('../nearby_checkins.php', {
                csrf_token: csrfToken,
                latitude: currentPosition.latitude,
                longitude: currentPosition.longitude
            })
                .then((data) => {
                    if (!data.ok) {
                        showStatus(data.message || 'Could not find nearby check-ins.', true);
                        return;
                    }

                    showStatus(`Found ${data.locations.length} nearby check-in location${data.locations.length === 1 ? '' : 's'}.`, false);
                    renderLocations(data.locations);
                })
                .catch(() => {
                    showStatus('Could not find nearby check-ins. Please try again.', true);
                })
                .finally(() => {
                    findButton.classList.remove('is-loading');
                    findButton.disabled = false;
                    findButton.textContent = 'Find Nearby Check-ins';
                });
        }

        function handleLocationError(error) {
            showStatus(locationErrorMessage(error), true);
            findButton.disabled = false;
            findButton.textContent = 'Find Nearby Check-ins';
        }
    });
}());
