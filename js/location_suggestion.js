(function () {
    const toggle = document.querySelector('[data-manual-location-toggle]');
    const form = document.querySelector('[data-manual-location-form]');

    if (!toggle || !form) {
        return;
    }

    function setManualFormVisible(isVisible) {
        form.hidden = !isVisible;
        form.classList.toggle('is-visible', isVisible);
        toggle.setAttribute('aria-expanded', isVisible ? 'true' : 'false');
    }

    setManualFormVisible(form.classList.contains('is-visible') || toggle.getAttribute('aria-expanded') === 'true');

    toggle.addEventListener('click', function () {
        setManualFormVisible(form.hidden);
        if (form.hidden) {
            return;
        }

        window.requestAnimationFrame(function () {
            form.querySelector('input, select, textarea')?.focus();
        });
    });
})();
