(function () {
    if (!window.matchMedia || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const selector = [
        '.business-list-item',
        '.event-feed-item',
        '.business-event-preview',
        '.business-review-card',
        '.business-photo-card',
        '.event-calendar-event',
        '.business-preview-card'
    ].join(',');

    const shouldUseInView = window.matchMedia('(hover: none)').matches;
    const mobileItems = new Set();
    let ticking = false;

    function revealMobileItemsNearCenter() {
        ticking = false;

        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        const centerLine = viewportHeight / 2;
        const centerBand = viewportHeight * 0.34;

        mobileItems.forEach((item) => {
            if (item.classList.contains('is-in-view')) {
                mobileItems.delete(item);
                return;
            }

            const rect = item.getBoundingClientRect();
            const itemCenter = rect.top + (rect.height / 2);
            const itemTouchesCenterBand = rect.bottom >= centerLine - centerBand && rect.top <= centerLine + centerBand;
            const itemWasPassed = rect.bottom < centerLine;

            if (itemTouchesCenterBand || itemWasPassed || Math.abs(itemCenter - centerLine) <= centerBand) {
                item.classList.add('is-in-view');
                mobileItems.delete(item);
            }
        });
    }

    function requestMobileRevealCheck() {
        if (ticking) {
            return;
        }

        ticking = true;
        window.requestAnimationFrame(revealMobileItemsNearCenter);
    }

    function hydrateDepthAnimations(root) {
        const animatedItems = root.querySelectorAll ? root.querySelectorAll(selector) : [];

        animatedItems.forEach((item) => {
            if (item.classList.contains('depth-animate')) {
                return;
            }

            item.classList.add('depth-animate');

            if (shouldUseInView) {
                mobileItems.add(item);
                requestMobileRevealCheck();
            } else {
                item.classList.add('is-in-view');
            }
        });
    }

    hydrateDepthAnimations(document);

    const mutationObserver = new MutationObserver((entries) => {
        entries.forEach((entry) => {
            entry.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    hydrateDepthAnimations(node);
                }
            });
        });
    });

    mutationObserver.observe(document.body, {
        childList: true,
        subtree: true
    });

    if (shouldUseInView) {
        window.addEventListener('scroll', requestMobileRevealCheck, { passive: true });
        window.addEventListener('resize', requestMobileRevealCheck);
        window.addEventListener('orientationchange', requestMobileRevealCheck);
        window.setTimeout(requestMobileRevealCheck, 250);
    }
}());
