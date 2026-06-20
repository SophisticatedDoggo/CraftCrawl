(function () {
    var widget = document.querySelector('[data-dashboard-checkin]');

    if (!widget) {
        return;
    }

    var findButton = widget.querySelector('[data-find-checkins]');
    var list = widget.querySelector('[data-checkin-list]');
    var feedback = widget.querySelector('[data-checkin-status]');
    var photoInput = widget.querySelector('[data-checkin-photo-input]');
    var modal = widget.querySelector('[data-checkin-modal]');
    var prompt = modal ? modal.querySelector('[data-checkin-prompt]') : null;
    var promptName = modal ? modal.querySelector('[data-checkin-prompt-name]') : null;
    var promptLocation = modal ? modal.querySelector('[data-checkin-prompt-location]') : null;
    var promptXp = modal ? modal.querySelector('[data-checkin-prompt-xp]') : null;
    var closeButton = modal ? modal.querySelector('[data-checkin-close]') : null;
    var takePhotoButton = modal ? modal.querySelector('[data-checkin-take-photo]') : null;
    var preview = modal ? modal.querySelector('[data-checkin-preview]') : null;
    var previewTitle = modal ? modal.querySelector('[data-checkin-preview-title]') : null;
    var previewDetail = modal ? modal.querySelector('[data-checkin-preview-detail]') : null;
    var previewImg = modal ? modal.querySelector('[data-checkin-preview-img]') : null;
    var retakeButton = modal ? modal.querySelector('[data-checkin-retake]') : null;
    var confirmButton = modal ? modal.querySelector('[data-checkin-confirm]') : null;
    var csrfToken = widget.dataset.csrfToken || '';
    var currentPosition = null;
    var pendingCheckin = null;
    var previewObjectUrl = null;

    function getFreshPosition() {
        return new Promise(function (resolve, reject) {
            var provider = window.CraftCrawlLocation || null;
            if (provider) {
                var started = provider.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true, timeout: 10000, maximumAge: 0
                });
                if (!started) {
                    reject(new Error('Location not available.'));
                }
            } else if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true, timeout: 10000, maximumAge: 0
                });
            } else {
                reject(new Error('Location not available.'));
            }
        });
    }

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

    function showModal(verifyData) {
        if (!modal) {
            return;
        }
        if (prompt) {
            prompt.hidden = false;
        }
        if (preview) {
            preview.hidden = true;
        }

        if (verifyData) {
            if (promptName) {
                promptName.textContent = verifyData.business_name || '';
            }
            if (promptLocation) {
                promptLocation.textContent = [verifyData.city, verifyData.state].filter(Boolean).join(', ');
            }
            if (promptXp) {
                var isFirst = verifyData.visit_type === 'first_time';
                var label = isFirst ? 'First visit' : 'Welcome back';
                promptXp.textContent = label + ' · +' + (verifyData.xp_awarded || 0) + ' XP';
                promptXp.className = 'checkin-prompt-xp' + (isFirst ? ' checkin-prompt-xp-first' : '');
            }
        }

        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function showPreview(photoFile) {
        if (!preview || !previewImg || !pendingCheckin) {
            return;
        }

        var data = pendingCheckin.verifyData;
        var visitLabel = data.visit_type === 'first_time' ? ' for the first time' : '';
        previewTitle.textContent = 'Checked in at ' + data.business_name + visitLabel;
        previewDetail.textContent = [data.city, data.state].filter(Boolean).join(', ');

        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
        }
        previewObjectUrl = URL.createObjectURL(photoFile);
        previewImg.src = previewObjectUrl;

        if (prompt) {
            prompt.hidden = true;
        }
        preview.hidden = false;
    }

    function hideModal() {
        if (modal) {
            modal.hidden = true;
        }
        document.body.style.overflow = '';
        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = null;
        }
    }

    function cancelModal() {
        if (pendingCheckin && pendingCheckin.button) {
            pendingCheckin.button.disabled = false;
        }
        hideModal();
        pendingCheckin = null;
    }

    if (closeButton) {
        closeButton.addEventListener('click', cancelModal);
    }

    if (modal) {
        modal.querySelector('.checkin-modal-scrim').addEventListener('click', cancelModal);
    }

    if (takePhotoButton) {
        takePhotoButton.addEventListener('click', function () {
            if (photoInput) {
                photoInput.value = '';
                photoInput.click();
            }
        });
    }

    // After photo is taken, switch to preview
    photoInput.addEventListener('change', function () {
        if (!pendingCheckin || !photoInput.files || !photoInput.files.length) {
            return;
        }
        showPreview(photoInput.files[0]);
    });

    // Retake
    if (retakeButton) {
        retakeButton.addEventListener('click', function () {
            if (photoInput) {
                photoInput.value = '';
                photoInput.click();
            }
        });
    }

    // Confirm — re-verify location, resize and submit
    if (confirmButton) {
        confirmButton.addEventListener('click', async function () {
            if (!pendingCheckin || !photoInput.files || !photoInput.files.length) {
                return;
            }

            confirmButton.disabled = true;
            confirmButton.classList.add('is-loading');
            confirmButton.textContent = 'Verifying location...';

            var freshPosition;
            try {
                freshPosition = await getFreshPosition();
            } catch (locErr) {
                hideModal();
                showStatus(locationErrorMessage(locErr), true);
                if (pendingCheckin && pendingCheckin.button) {
                    pendingCheckin.button.disabled = false;
                }
                confirmButton.disabled = false;
                confirmButton.classList.remove('is-loading');
                confirmButton.textContent = 'Post Check-in';
                pendingCheckin = null;
                return;
            }

            confirmButton.textContent = 'Posting...';

            var formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('location_id', pendingCheckin.location.id);
            formData.append('latitude', freshPosition.coords.latitude);
            formData.append('longitude', freshPosition.coords.longitude);

            try {
                var photo = photoInput.files[0];
                if (window.CraftCrawlResizePhoto) {
                    photo = await window.CraftCrawlResizePhoto(photo);
                }
                formData.append('checkin_photo', photo);
            } catch (err) {
                hideModal();
                showStatus('Photo could not be processed. Please try again.', true);
                confirmButton.disabled = false;
                confirmButton.classList.remove('is-loading');
                confirmButton.textContent = 'Post Check-in';
                return;
            }

            var actionButton = pendingCheckin.button;

            var controller = new AbortController();
            var timeoutId = setTimeout(function () { controller.abort(); }, 45000);

            fetch('../check_in.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                signal: controller.signal
            })
                .then(function (response) {
                    clearTimeout(timeoutId);
                    return response.json();
                })
                .then(function (data) {
                    hideModal();

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
                .catch(function (err) {
                    hideModal();
                    if (err && err.name === 'AbortError') {
                        showStatus('Check-in timed out. Move to a stronger signal and try again.', true);
                    } else {
                        showStatus('Check-in failed. Please try again.', true);
                    }
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

            var isOnCooldown = !location.eligible && location.is_open && location.eligible_at;
            meta.textContent = formatBusinessType(location.type) + ' · ' + location.city + ', ' + location.state + ' · ' + formatDistance(location.distance_meters) + ' · ' + (location.eligible ? visitText : location.unavailable_reason);
            action.type = 'button';
            action.disabled = !location.eligible;

            if (isOnCooldown) {
                action.className = 'checkin-cooldown-btn';
                var cooldownSpan = document.createElement('span');
                cooldownSpan.setAttribute('data-checkin-cooldown-label', '');
                cooldownSpan.textContent = 'On Cooldown';
                action.appendChild(cooldownSpan);
                if (window.CraftCrawlCooldownTimer) {
                    window.CraftCrawlCooldownTimer.start(cooldownSpan, location.eligible_at, function () {
                        action.disabled = false;
                        action.className = '';
                        action.textContent = 'Check In';
                    });
                }
            } else {
                action.textContent = location.eligible ? 'Check In' : (location.is_open ? 'On Cooldown' : 'Closed');
            }

            // Step 1: Click "Check In" → verify → show modal
            action.addEventListener('click', function () {
                action.disabled = true;
                action.classList.add('is-loading');
                action.textContent = 'Checking in...';

                var verifyFormData = new FormData();
                verifyFormData.append('csrf_token', csrfToken);
                verifyFormData.append('location_id', location.id);
                verifyFormData.append('latitude', currentPosition.latitude);
                verifyFormData.append('longitude', currentPosition.longitude);

                fetch('../check_in_verify.php', {
                    method: 'POST',
                    body: verifyFormData,
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
                        action.classList.remove('is-loading');
                        action.textContent = 'Check In';
                        showModal(data);
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
