window.CraftCrawlInitReviewEditToggle = function (root = document) {
    const preview = root.querySelector('[data-review-edit-preview]');
    const form = root.querySelector('[data-review-edit-form]');
    const toggle = root.querySelector('[data-review-edit-toggle]');
    const cancel = root.querySelector('[data-review-edit-cancel]');

    if (!preview || !form || !toggle || toggle.dataset.reviewEditReady === 'true') {
        return false;
    }
    toggle.dataset.reviewEditReady = 'true';

    function showForm() {
        preview.hidden = true;
        form.hidden = false;
        form.querySelector('select, textarea, input')?.focus();
    }

    function hideForm() {
        form.hidden = true;
        preview.hidden = false;
        toggle.focus();
    }

    toggle.addEventListener('click', showForm);

    if (cancel) {
        cancel.addEventListener('click', hideForm);
    }
    return true;
};

window.CraftCrawlInitReviewEditToggle();
