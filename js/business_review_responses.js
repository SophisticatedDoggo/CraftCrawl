const responseToggles = document.querySelectorAll('[data-response-toggle]');

responseToggles.forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const reviewId = toggle.dataset.responseToggle;
        const form = document.querySelector(`[data-response-form="${reviewId}"]`);

        if (!form) {
            return;
        }

        const isOpening = form.hidden;
        form.hidden = !isOpening;
        toggle.textContent = isOpening ? 'Cancel' : toggle.dataset.responseLabel;

        if (isOpening) {
            const textarea = form.querySelector('textarea');

            if (textarea) {
                textarea.focus();
            }
        }
    });
});
