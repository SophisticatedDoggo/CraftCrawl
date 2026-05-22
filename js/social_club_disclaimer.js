(() => {
    const modal = document.querySelector('[data-social-club-disclaimer]');
    if (!modal) return;

    const dismissButton = modal.querySelector('[data-social-club-disclaimer-dismiss]');

    document.body.classList.add('welcome-modal-open');
    dismissButton?.focus();

    function dismiss() {
        modal.classList.add('is-closing');
        document.body.classList.remove('welcome-modal-open');
        window.setTimeout(() => modal.remove(), 180);
    }

    dismissButton?.addEventListener('click', dismiss);
})();
