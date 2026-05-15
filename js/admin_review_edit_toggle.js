(function () {
    document.querySelectorAll('[data-admin-review-card]').forEach((card) => {
        const preview = card.querySelector('[data-admin-review-preview]');
        const form = card.querySelector('[data-admin-review-edit-form]');
        const toggle = card.querySelector('[data-admin-review-edit-toggle]');
        const cancel = card.querySelector('[data-admin-review-edit-cancel]');

        if (!preview || !form || !toggle) {
            return;
        }

        function showForm() {
            preview.hidden = true;
            form.hidden = false;
            toggle.textContent = 'Editing';
            toggle.disabled = true;
            form.querySelector('select, textarea, input')?.focus();
        }

        function hideForm() {
            form.hidden = true;
            preview.hidden = false;
            toggle.textContent = 'Edit';
            toggle.disabled = false;
            toggle.focus();
        }

        toggle.addEventListener('click', showForm);
        cancel?.addEventListener('click', hideForm);
    });
}());
