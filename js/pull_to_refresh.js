(function () {
    const THRESHOLD = 72;
    const MAX_PULL = 118;

    function init(scope = document) {
        scope.querySelectorAll('[data-pull-refresh]').forEach((root) => {
            if (root.dataset.pullRefreshReady === 'true') {
                return;
            }

            root.dataset.pullRefreshReady = 'true';
            const indicator = root.querySelector('[data-refresh-indicator]');
            const label = indicator?.querySelector('[data-refresh-label]');
            const buttons = root.querySelectorAll('[data-refresh-button]');
            let touchStartY = 0;
            let pullDistance = 0;
            let tracking = false;
            let refreshing = false;

            function canPull() {
                return window.scrollY <= 0 && !refreshing;
            }

            function setPull(distance) {
                pullDistance = Math.max(0, Math.min(distance, MAX_PULL));
                root.style.setProperty('--pull-refresh-distance', `${pullDistance}px`);
                root.classList.toggle('is-pulling', pullDistance > 0);
                root.classList.toggle('is-ready-to-refresh', pullDistance >= THRESHOLD);

                if (label && !refreshing) {
                    label.textContent = pullDistance >= THRESHOLD ? 'Release to refresh' : 'Pull to refresh';
                }
            }

            function resetPull() {
                setPull(0);
                root.classList.remove('is-pulling', 'is-ready-to-refresh');
            }

            function pageRefresh() {
                const action = root.dataset.refreshAction || 'reload';

                if (action === 'feed' && typeof window.CraftCrawlRefreshFriendsFeed === 'function') {
                    return window.CraftCrawlRefreshFriendsFeed();
                }

                window.location.reload();
                return new Promise(() => {});
            }

            function refresh() {
                if (refreshing) {
                    return;
                }

                refreshing = true;
                root.classList.add('is-refreshing');
                if (label) {
                    label.textContent = 'Refreshing…';
                }

                Promise.resolve(pageRefresh())
                    .catch(() => {})
                    .finally(() => {
                        refreshing = false;
                        root.classList.remove('is-refreshing');
                        resetPull();
                        if (label) {
                            label.textContent = 'Pull to refresh';
                        }
                    });
            }

            buttons.forEach((button) => {
                button.addEventListener('click', refresh);
            });

            root.addEventListener('touchstart', (event) => {
                if (!canPull() || event.touches.length !== 1) {
                    return;
                }

                tracking = true;
                touchStartY = event.touches[0].clientY;
            }, { passive: true });

            root.addEventListener('touchmove', (event) => {
                if (!tracking || event.touches.length !== 1) {
                    return;
                }

                const delta = event.touches[0].clientY - touchStartY;
                if (delta <= 0) {
                    resetPull();
                    return;
                }

                if (!canPull()) {
                    tracking = false;
                    resetPull();
                    return;
                }

                event.preventDefault();
                setPull(delta * 0.55);
            }, { passive: false });

            root.addEventListener('touchend', () => {
                if (!tracking) {
                    return;
                }

                tracking = false;
                if (pullDistance >= THRESHOLD) {
                    refresh();
                    return;
                }

                resetPull();
            });

            root.addEventListener('touchcancel', () => {
                tracking = false;
                resetPull();
            });
        });
    }

    window.CraftCrawlInitPullToRefresh = init;
    init();
})();
