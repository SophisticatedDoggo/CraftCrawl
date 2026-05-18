(function () {
    const toggle = document.querySelector('.business-manual-location-toggle');
    const form = document.querySelector('.business-add-location-form');
    if (!toggle || !form) return;
    toggle.addEventListener('toggle', function () {
        form.classList.toggle('is-visible', toggle.open);
    });
})();
