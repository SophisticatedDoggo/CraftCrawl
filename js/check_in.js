window.CraftCrawlInitCheckIn = function (root = document) {
    var form = root.querySelector('[data-check-in-form]');

    if (!form || form.dataset.checkInReady === 'true') {
        return false;
    }
    form.dataset.checkInReady = 'true';

    var button = form.querySelector('button[type="submit"]');
    var feedback = root.querySelector('[data-check-in-feedback]');
    var latitudeInput = form.querySelector('input[name="latitude"]');
    var longitudeInput = form.querySelector('input[name="longitude"]');
    var photoInput = form.querySelector('[data-checkin-photo-input]');

    var modal = root.querySelector('[data-checkin-modal]');
    var prompt = modal ? modal.querySelector('[data-checkin-prompt]') : null;
    var takePhotoButton = modal ? modal.querySelector('[data-checkin-take-photo]') : null;
    var preview = modal ? modal.querySelector('[data-checkin-preview]') : null;
    var previewTitle = modal ? modal.querySelector('[data-checkin-preview-title]') : null;
    var previewDetail = modal ? modal.querySelector('[data-checkin-preview-detail]') : null;
    var previewImg = modal ? modal.querySelector('[data-checkin-preview-img]') : null;
    var retakeButton = modal ? modal.querySelector('[data-checkin-retake]') : null;
    var confirmButton = modal ? modal.querySelector('[data-checkin-confirm]') : null;

    var verifyData = null;
    var previewObjectUrl = null;

    function showFeedback(message, isError) {
        if (!feedback) {
            return;
        }

        feedback.textContent = message;
        feedback.classList.toggle('form-message-error', isError);
        feedback.classList.toggle('form-message-success', !isError);
        feedback.hidden = false;
    }

    function hideFeedback() {
        if (feedback) {
            feedback.hidden = true;
        }
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

    function resetButton(originalText) {
        if (button) {
            button.disabled = false;
            button.classList.remove('is-loading');
            button.textContent = originalText;
        }
    }

    function showModal() {
        if (!modal) {
            return;
        }
        if (prompt) {
            prompt.hidden = false;
        }
        if (preview) {
            preview.hidden = true;
        }
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function showPreview(photoFile) {
        if (!preview || !previewImg || !verifyData) {
            return;
        }

        var visitLabel = verifyData.visit_type === 'first_time' ? ' for the first time' : '';
        previewTitle.textContent = 'Checked in at ' + verifyData.business_name + visitLabel;
        previewDetail.textContent = [verifyData.city, verifyData.state].filter(Boolean).join(', ');

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

    // "Take Photo" button in the modal — opens camera from a user gesture
    if (takePhotoButton) {
        takePhotoButton.addEventListener('click', function () {
            if (photoInput) {
                photoInput.value = '';
                photoInput.click();
            }
        });
    }

    // After photo is taken, switch from prompt to preview
    if (photoInput) {
        photoInput.addEventListener('change', function () {
            if (!verifyData || !photoInput.files || !photoInput.files.length) {
                return;
            }
            showPreview(photoInput.files[0]);
        });
    }

    // Retake button
    if (retakeButton) {
        retakeButton.addEventListener('click', function () {
            if (photoInput) {
                photoInput.value = '';
                photoInput.click();
            }
        });
    }

    // Confirm button — resize photo and submit
    if (confirmButton) {
        confirmButton.addEventListener('click', async function () {
            if (!verifyData || !photoInput.files || !photoInput.files.length) {
                return;
            }

            confirmButton.disabled = true;
            confirmButton.classList.add('is-loading');
            confirmButton.textContent = 'Posting...';

            var formData = new FormData();
            formData.append('csrf_token', form.querySelector('input[name="csrf_token"]').value);
            formData.append('business_id', form.querySelector('input[name="business_id"]').value);
            formData.append('location_id', form.querySelector('input[name="location_id"]').value);
            formData.append('latitude', latitudeInput.value);
            formData.append('longitude', longitudeInput.value);

            try {
                var photo = photoInput.files[0];
                if (window.CraftCrawlResizePhoto) {
                    photo = await window.CraftCrawlResizePhoto(photo);
                }
                formData.append('checkin_photo', photo);
            } catch (err) {
                hideModal();
                showFeedback('Photo could not be processed. Please try again.', true);
                confirmButton.disabled = false;
                confirmButton.classList.remove('is-loading');
                confirmButton.textContent = 'Post Check-in';
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
                    hideModal();

                    if (!data.ok) {
                        showFeedback(data.message || 'Check-in failed.', true);
                        return;
                    }

                    var badgeText = data.badges && data.badges.length
                        ? ' Badges earned: ' + data.badges.join(', ') + '.'
                        : '';
                    var checkinMessageText = data.checkin_message
                        ? ' ' + data.checkin_message
                        : '';

                    showFeedback(data.message + badgeText + checkinMessageText, false);
                    window.CraftCrawlMarkQuestPanelStale?.();
                    window.dispatchEvent(new CustomEvent('craftcrawl:quest-progress-changed', {
                        detail: { source: 'check_in', questRewards: data.quest_rewards || [] }
                    }));

                    if (window.craftcrawlShowXpReward) {
                        window.craftcrawlShowXpReward(data);
                    }
                })
                .catch(function () {
                    hideModal();
                    showFeedback('Check-in failed. Please try again.', true);
                })
                .finally(function () {
                    confirmButton.disabled = false;
                    confirmButton.classList.remove('is-loading');
                    confirmButton.textContent = 'Post Check-in';
                    verifyData = null;
                });
        });
    }

    // Step 1: Click "Check In" → verify proximity + hours → show modal
    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var locationProvider = window.CraftCrawlLocation || null;

        if (!locationProvider && !navigator.geolocation) {
            showFeedback('Your browser does not support location check-ins.', true);
            return;
        }

        var originalText = button ? button.textContent : '';

        if (button) {
            button.disabled = true;
            button.classList.add('is-loading');
            button.textContent = 'Checking in...';
        }

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
            showFeedback('Your browser does not support location check-ins.', true);
            resetButton(originalText);
        }

        function handlePosition(position) {
            latitudeInput.value = position.coords.latitude;
            longitudeInput.value = position.coords.longitude;

            var verifyFormData = new FormData(form);

            fetch('check_in_verify.php', {
                method: 'POST',
                body: verifyFormData,
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    resetButton(originalText);

                    if (!data.ok) {
                        showFeedback(data.message || 'Check-in failed.', true);
                        return;
                    }

                    verifyData = data;
                    showModal();
                })
                .catch(function () {
                    resetButton(originalText);
                    showFeedback('Check-in failed. Please try again.', true);
                });
        }

        function handleLocationError(error) {
            showFeedback(locationErrorMessage(error), true);
            resetButton(originalText);
        }
    });
    return true;
};

window.CraftCrawlInitCheckIn();
