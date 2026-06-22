(function () {
    var page = document.querySelector('.business-details-page');
    if (!page) return;

    function switchTab(target) {
        page.querySelectorAll('[data-business-subtab]').forEach(function (tab) {
            var isTarget = tab.dataset.businessSubtab === target;
            tab.classList.toggle('is-active', isTarget);
            tab.setAttribute('aria-selected', isTarget ? 'true' : 'false');
        });

        page.querySelectorAll('[data-business-subtab-panel]').forEach(function (panel) {
            panel.hidden = panel.dataset.businessSubtabPanel !== target;
        });

        var url = new URL(window.location.href);
        if (target === 'info') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', target);
        }
        history.replaceState(null, '', url.toString());

        page.querySelectorAll('input[name="current_tab"]').forEach(function (input) {
            input.value = target;
        });
    }

    page.addEventListener('click', function (e) {
        var tab = e.target.closest('[data-business-subtab]');
        if (tab) switchTab(tab.dataset.businessSubtab);
    });

    var urlParams = new URLSearchParams(window.location.search);
    var initialTab = urlParams.get('tab');
    if (initialTab && page.querySelector('[data-business-subtab="' + initialTab + '"]')) {
        switchTab(initialTab);
    }
}());
