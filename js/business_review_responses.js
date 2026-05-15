window.CraftCrawlInitBusinessReviewResponses = function (root = document) {
    root.querySelectorAll('[data-response-toggle]').forEach((toggle) => {
        if (toggle.dataset.responseReady === 'true') return;
        toggle.dataset.responseReady = 'true';
        toggle.addEventListener('click', () => {
            const reviewId = toggle.dataset.responseToggle;
            const form = root.querySelector(`[data-response-form="${reviewId}"]`);
            if (!form) return;
            const isOpening = form.hidden;
            form.hidden = !isOpening;
            toggle.textContent = isOpening ? 'Cancel' : toggle.dataset.responseLabel;
            if (isOpening) form.querySelector('textarea')?.focus();
        });
    });
};
window.CraftCrawlInitBusinessReviewResponses();
