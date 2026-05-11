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

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!navigator.geolocation) {
            showFeedback('Your browser does not support location check-ins.', true);
            return;
        }

        const originalText = button ? button.textContent : '';

        if (button) {
            button.disabled = true;
            button.textContent = 'Checking location...';
        }

        navigator.geolocation.getCurrentPosition(function (position) {
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
                    const levelText = data.progress
                        ? ` Level ${data.progress.level} - ${data.progress.title}.`
                        : '';

                    showFeedback(`${data.message} +${data.xp_awarded} XP.${levelText}${badgeText}`, false);
                })
                .catch(function () {
                    showFeedback('Check-in failed. Please try again.', true);
                })
                .finally(function () {
                    if (button) {
                        button.disabled = false;
                        button.textContent = originalText;
                    }
                });
        }, function () {
            showFeedback('Location permission is required to check in.', true);

            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
        }, {
            enableHighAccuracy: true,
            timeout: 12000,
            maximumAge: 0
        });
    });
}());
