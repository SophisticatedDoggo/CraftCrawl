(function () {
    var widget = document.querySelector('[data-dashboard-checkin]');

    if (!widget) {
        return;
    }

    var findButton = widget.querySelector('[data-find-checkins]');
    var list = widget.querySelector('[data-checkin-list]');
    var feedback = widget.querySelector('[data-checkin-status]');
    var photoInput = widget.querySelector('[data-checkin-photo-input]');
    var preview = widget.querySelector('[data-checkin-preview]');
    var previewTitle = preview ? preview.querySelector('[data-checkin-preview-title]') : null;
    var previewDetail = preview ? preview.querySelector('[data-checkin-preview-detail]') : null;
    var previewImg = preview ? preview.querySelector('[data-checkin-preview-img]') : null;
    var retakeButton = preview ? preview.querySelector('[data-checkin-retake]') : null;
    var confirmButton = preview ? preview.querySelector('[data-checkin-confirm]') : null;
    var csrfToken = widget.dataset.csrfToken || '';
    var currentPosition = null;
    var pendingCheckin = null;
    var previewObjectUrl = null;

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
        var labels = {
            brewery: 'Brewery',
            winery: 'Winery',
            cidery: 'Cidery',
            distillery: 'Distillery',
            distilery: 'Distillery',
            meadery: 'Meadery',
            bar: 'Bar',
            social_club: 'Social Club'
        };

        return labels[type] || 'Business';
    }

    function formatDistance(meters) {
        if (meters < 160) {
            return meters + ' m';
        }

        return (meters / 1609.344).toFixed(2) + ' mi';
    }

    function showPreview(data, photoFile) {
        if (!preview || !previewImg) {
            return;
        }

        var visitLabel = data.visit_type === 'first_time' ? ' for the first time' : '';
        previewTitle.textContent = 'Checked in at ' + data.business_name + visitLabel;
        previewDetail.textContent = [data.city, data.state].filter(Boolean).join(', ');

        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
        }
        previewObjectUrl = URL.createObjectURL(photoFile);
        previewImg.src = previewObjectUrl;

        list.hidden = true;
        feedback.hidden = true;
        preview.hidden = false;
    }

    function hidePreview() {
        if (preview) {
            preview.hidden = true;
        }
        list.hidden = false;
        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = null;
        }
    }

    function openCamera() {
        if (photoInput) {
            photoInput.value = '';
            photoInput.click();
        }
    }

    // Step 2b: Photo taken — show preview
    photoInput.addEventListener('change', function () {
        if (!pendingCheckin || !photoInput.files || !photoInput.files.length) {
            return;
        }

        showPreview(pendingCheckin.verifyData, photoInput.files[0]);
    });

    // Retake
    if (retakeButton) {
        retakeButton.addEventListener('click', function () {
            openCamera();
        });
    }

    // Confirm — resize and submit
    if (confirmButton) {
        confirmButton.addEventListener('click', async function () {
            if (!pendingCheckin || !photoInput.files || !photoInput.files.length) {
                return;
            }

            confirmButton.disabled = true;
            confirmButton.classList.add('is-loading');
            confirmButton.textContent = 'Posting...';

            var formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('location_id', pendingCheckin.location.id);
            formData.append('latitude', currentPosition.latitude);
            formData.append('longitude', currentPosition.longitude);

            try {
                var photo = photoInput.files[0];
                if (window.CraftCrawlResizePhoto) {
                    photo = await window.CraftCrawlResizePhoto(photo);
                }
                formData.append('checkin_photo', photo);
            } catch (err) {
                hidePreview();
                showStatus('Photo could not be processed. Please try again.', true);
                confirmButton.disabled = false;
                confirmButton.classList.remove('is-loading');
                confirmButton.textContent = 'Post Check-in';
                return;
            }

            var actionButton = pendingCheckin.button;

            fetch('../check_in.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    hidePreview();

                    if (!data.ok) {
                        showStatus(data.message || 'Check-in failed.', true);
                        if (actionButton) {
                            actionButton.disabled = false;
                            actionButton.classList.remove('is-loading');
                            actionButton.textContent = 'Check In';
                        }
                        return;
                    }

                    var badgeText = data.badges && data.badges.length
                        ? ' Badges earned: ' + data.badges.join(', ') + '.'
                        : '';

                    showStatus(data.message + badgeText, false);
                    window.CraftCrawlMarkQuestPanelStale?.();
                    window.dispatchEvent(new CustomEvent('craftcrawl:quest-progress-changed', {
                        detail: { source: 'check_in', questRewards: data.quest_rewards || [] }
                    }));
                    if (window.craftcrawlShowXpReward) {
                        window.craftcrawlShowXpReward(data);
                    }
                    if (actionButton) {
                        actionButton.textContent = 'Checked In';
                        actionButton.classList.remove('is-loading');
                    }
                })
                .catch(function () {
                    hidePreview();
                    showStatus('Check-in failed. Please try again.', true);
                    if (actionButton) {
                        actionButton.disabled = false;
                        actionButton.classList.remove('is-loading');
                        actionButton.textContent = 'Check In';
                    }
                })
                .finally(function () {
                    confirmButton.disabled = false;
                    confirmButton.classList.remove('is-loading');
                    confirmButton.textContent = 'Post Check-in';
                    pendingCheckin = null;
                });
        });
    }

    function renderLocations(locations) {
        list.innerHTML = '';

        if (!locations.length) {
            list.hidden = true;
            showStatus('No check-in locations found nearby. Move closer to a listed location and try again.', true);
            return;
        }

        locations.forEach(function (location) {
            var item = document.createElement('article');
            var details = document.createElement('div');
            var title = document.createElement('strong');
            var meta = document.createElement('span');
            var action = document.createElement('button');

            item.className = 'dashboard-checkin-item';
            title.textContent = location.name;

            var visitText = location.visit_type === 'first_time'
                ? 'First-time check-in · +' + location.xp_awarded + ' XP'
                : 'Repeat check-in · +' + location.xp_awarded + ' XP';

            meta.textContent = formatBusinessType(location.type) + ' · ' + location.city + ', ' + location.state + ' · ' + formatDistance(location.distance_meters) + ' · ' + (location.eligible ? visitText : location.unavailable_reason);
            action.type = 'button';
            action.textContent = location.eligible ? 'Check In' : (location.is_open ? 'On Cooldown' : 'Closed');
            action.disabled = !location.eligible;

            // Step 1: Click "Check In" → verify → camera
            action.addEventListener('click', function () {
                action.disabled = true;
                action.classList.add('is-loading');
                action.textContent = 'Verifying...';

                var verifyData = new FormData();
                verifyData.append('csrf_token', csrfToken);
                verifyData.append('location_id', location.id);
                verifyData.append('latitude', currentPosition.latitude);
                verifyData.append('longitude', currentPosition.longitude);

                fetch('../check_in_verify.php', {
                    method: 'POST',
                    body: verifyData,
                    credentials: 'same-origin'
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (!data.ok) {
                            showStatus(data.message || 'Check-in failed.', true);
                            action.disabled = false;
                            action.classList.remove('is-loading');
                            action.textContent = 'Check In';
                            return;
                        }

                        pendingCheckin = {
                            location: location,
                            button: action,
                            verifyData: data
                        };
                        action.textContent = 'Take Photo...';
                        openCamera();
                    })
                    .catch(function () {
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

    findButton.addEventListener('click', function () {
        var locationProvider = window.CraftCrawlLocation || null;

        if (!locationProvider && !navigator.geolocation) {
            showStatus('Your browser does not support location check-ins.', true);
            return;
        }

        findButton.disabled = true;
        findButton.classList.add('is-loading');
        findButton.textContent = 'Finding nearby...';
        list.hidden = true;

        var didStartLocationRequest = true;

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

            var formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('latitude', currentPosition.latitude);
            formData.append('longitude', currentPosition.longitude);

            fetch('../nearby_checkins.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        showStatus(data.message || 'Could not find nearby check-ins.', true);
                        return;
                    }

                    showStatus('Found ' + data.locations.length + ' nearby check-in location' + (data.locations.length === 1 ? '' : 's') + '.', false);
                    renderLocations(data.locations);
                })
                .catch(function () {
                    showStatus('Could not find nearby check-ins. Please try again.', true);
                })
                .finally(function () {
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
