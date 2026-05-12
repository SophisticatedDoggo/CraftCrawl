function setupPasswordVisibilityToggles() {
    const toggles = document.querySelectorAll('[data-password-toggle]');

    toggles.forEach((toggle) => {
        if (toggle.dataset.ready === 'true') {
            return;
        }

        const field = document.getElementById(toggle.dataset.passwordToggle);

        if (!field) {
            return;
        }

        toggle.dataset.ready = 'true';

        toggle.addEventListener('click', () => {
            const showing = field.type === 'text';
            field.type = showing ? 'password' : 'text';
            toggle.classList.toggle('is-visible', !showing);
            toggle.setAttribute('aria-pressed', String(!showing));
            toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    });
}

setupPasswordVisibilityToggles();
