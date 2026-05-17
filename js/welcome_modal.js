(() => {
    const modal = document.querySelector('[data-welcome-modal]');

    if (!modal) {
        return;
    }

    const dismissButton = modal.querySelector('[data-welcome-dismiss]');
    const dismissUrl = modal.dataset.dismissUrl;
    let isDismissing = false;

    document.body.classList.add('welcome-modal-open');
    dismissButton?.focus();

    async function dismissWelcome() {
        if (isDismissing) {
            return;
        }

        isDismissing = true;
        dismissButton?.setAttribute('disabled', 'disabled');

        try {
            const body = new URLSearchParams({
                csrf_token: window.CRAFTCRAWL_CSRF_TOKEN || ''
            });
            const response = await fetch(dismissUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            });

            if (!response.ok) {
                throw new Error('Unable to dismiss welcome modal.');
            }

            modal.classList.add('is-closing');
            document.body.classList.remove('welcome-modal-open');
            window.setTimeout(() => modal.remove(), 180);
        } catch (error) {
            isDismissing = false;
            dismissButton?.removeAttribute('disabled');
        }
    }

    dismissButton?.addEventListener('click', dismissWelcome);
})();
