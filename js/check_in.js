window.CraftCrawlInitCheckIn = function (root = document) {
    const form = root.querySelector('[data-check-in-form]');

    if (!form || form.dataset.checkInReady === 'true') {
        return false;
    }
    form.dataset.checkInReady = 'true';

    const button = form.querySelector('button[type="submit"]');
    const feedback = root.querySelector('[data-check-in-feedback]');
    const latitudeInput = form.querySelector('input[name="latitude"]');
    const longitudeInput = form.querySelector('input[name="longitude"]');
    const photoInput = form.querySelector('[data-checkin-photo-input]');

    function showFeedback(message, isError) {
        if (!feedback) {
            return;
        }

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

        return 'Location permission is required to check in.';
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!photoInput || !photoInput.files || !photoInput.files.length) {
            showFeedback('A photo is required to check in.', true);
            return;
        }

        const locationProvider = window.CraftCrawlLocation || null;

        if (!locationProvider && !navigator.geolocation) {
            showFeedback('Your browser does not support location check-ins.', true);
            return;
        }

        const originalText = button ? button.textContent : '';

        if (button) {
            button.disabled = true;
            button.classList.add('is-loading');
            button.textContent = 'Checking location...';
        }

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
            showFeedback('Your browser does not support location check-ins.', true);

            if (button) {
                button.disabled = false;
                button.classList.remove('is-loading');
                button.textContent = originalText;
            }
        }

        async function handlePosition(position) {
            latitudeInput.value = position.coords.latitude;
            longitudeInput.value = position.coords.longitude;

            if (button) {
                button.textContent = 'Uploading photo...';
            }

            var formData = new FormData(form);

            try {
                if (window.CraftCrawlResizePhoto) {
                    var resized = await window.CraftCrawlResizePhoto(photoInput.files[0]);
                    formData.set('checkin_photo', resized);
                }
            } catch (err) {
                showFeedback('Photo could not be processed. Please try again.', true);
                if (button) {
                    button.disabled = false;
                    button.classList.remove('is-loading');
                    button.textContent = originalText;
                }
                return;
            }

            fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!data.ok) {
                        showFeedback(data.message || 'Check-in failed.', true);
                        return;
                    }

                    const badgeText = data.badges && data.badges.length
                        ? ` Badges earned: ${data.badges.join(', ')}.`
                        : '';
                    const checkinMessageText = data.checkin_message
                        ? ` ${data.checkin_message}`
                        : '';

                    showFeedback(`${data.message}${badgeText}${checkinMessageText}`, false);
                    window.CraftCrawlMarkQuestPanelStale?.();
                    window.dispatchEvent(new CustomEvent('craftcrawl:quest-progress-changed', {
                        detail: { source: 'check_in', questRewards: data.quest_rewards || [] }
                    }));

                    if (window.craftcrawlShowXpReward) {
                        window.craftcrawlShowXpReward(data);
                    }
                })
                .catch(function () {
                    showFeedback('Check-in failed. Please try again.', true);
                })
                .finally(function () {
                    if (button) {
                        button.disabled = false;
                        button.classList.remove('is-loading');
                        button.textContent = originalText;
                    }
                });
        }

        function handleLocationError(error) {
            showFeedback(locationErrorMessage(error), true);

            if (button) {
                button.disabled = false;
                button.classList.remove('is-loading');
                button.textContent = originalText;
            }
        }
    });
    return true;
};

window.CraftCrawlInitCheckIn();
