(() => {
    const modal = document.querySelector('[data-portal-notice-modal]');
    if (!modal) return;

    const dismissButton = modal.querySelector('[data-portal-notice-dismiss]');

    document.body.classList.add('welcome-modal-open');
    dismissButton?.focus();

    function dismissNotice() {
        modal.classList.add('is-closing');
        document.body.classList.remove('welcome-modal-open');

        const url = new URL(window.location.href);
        url.searchParams.delete('message');
        window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);

        window.setTimeout(() => modal.remove(), 180);
    }

    dismissButton?.addEventListener('click', dismissNotice);
})();
