(function () {
    const preview = document.querySelector('[data-review-edit-preview]');
    const form = document.querySelector('[data-review-edit-form]');
    const toggle = document.querySelector('[data-review-edit-toggle]');
    const cancel = document.querySelector('[data-review-edit-cancel]');

    if (!preview || !form || !toggle) {
        return;
    }

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
}());
