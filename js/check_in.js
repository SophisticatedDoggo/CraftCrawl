(function () {
    const form = document.querySelector('[data-check-in-form]');

    if (!form) {
        return;
    }

    const button = form.querySelector('button[type="submit"]');
    const feedback = document.querySelector('[data-check-in-feedback]');
    const latitudeInput = form.querySelector('input[name="latitude"]');
    const longitudeInput = form.querySelector('input[name="longitude"]');

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

        function handlePosition(position) {
            latitudeInput.value = position.coords.latitude;
            longitudeInput.value = position.coords.longitude;

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
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
}());
