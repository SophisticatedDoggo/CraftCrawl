function notificationFocusTarget(root = document) {
    const params = new URLSearchParams(window.location.search);
    const commentId = params.get('focus_comment');
    const section = params.get('focus_section');

    if (commentId && /^\d+$/.test(commentId)) {
        return root.querySelector(`#comment-${CSS.escape(commentId)}`);
    }

    if (section === 'reactions') {
        return root.querySelector('#feed-reactions');
    }

    if (window.location.hash) {
        return root.querySelector(`#${CSS.escape(window.location.hash.slice(1))}`);
    }

    return null;
}

function focusNotificationTargetWhenVisible(target) {
    if (!target || target.dataset.notificationFocusHandled === 'true') {
        return;
    }

    target.dataset.notificationFocusHandled = 'true';
    target.classList.add('notification-focus-pending');

    const highlightTarget = () => {
        target.classList.remove('notification-focus-pending');
        target.classList.add('notification-focus-target');
    };

    const observer = new IntersectionObserver((entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) {
            return;
        }

        observer.disconnect();
        highlightTarget();
    }, { threshold: 0.45 });

    observer.observe(target);

    window.requestAnimationFrame(() => {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    window.setTimeout(() => {
        observer.disconnect();
        if (!target.classList.contains('notification-focus-target')) {
            highlightTarget();
        }
    }, 1800);
}

window.CraftCrawlInitFeedThread = function (root = document) {
    const threadPage = root.querySelector('.feed-thread-page');

    if (threadPage && threadPage.dataset.swipeDismissReady !== 'true') {
        threadPage.dataset.swipeDismissReady = 'true';
        const overlay = threadPage.closest('[data-feed-thread-overlay]');
        const overlayContent = overlay?.querySelector('[data-feed-thread-overlay-content]');

        const swipe = {
            active: false,
            pointerId: null,
            startX: 0,
            startY: 0,
            lastX: 0,
            dragging: false
        };

        function isSwipeIgnored(target) {
            return Boolean(target.closest('a, button, input, textarea, select, label, [role="button"], .feed-reaction-swipe, .feed-comment-form'));
        }

        function markThreadReturnAnchor() {
            const itemKey = threadPage.dataset.feedThreadItemKey || '';
            if (itemKey) {
                try {
                    sessionStorage.setItem('craftcrawlFeedThreadReturnItemKey', itemKey);
                } catch (_) {}
            }
        }

        threadPage.querySelectorAll('[data-back-link]').forEach((link) => {
            if (link.dataset.feedThreadReturnReady === 'true') return;
            link.dataset.feedThreadReturnReady = 'true';
            link.addEventListener('click', (event) => {
                markThreadReturnAnchor();
                if (overlay && typeof window.CraftCrawlCloseFeedThreadOverlay === 'function') {
                    event.preventDefault();
                    window.CraftCrawlCloseFeedThreadOverlay({ useHistory: true });
                }
            }, { capture: true });
        });

        function dismissThread() {
            if (overlay && typeof window.CraftCrawlCloseFeedThreadOverlay === 'function') {
                overlay.classList.add('is-swipe-dismissing');
                window.setTimeout(() => {
                    window.CraftCrawlCloseFeedThreadOverlay({
                        useHistory: true,
                        returnItemKey: threadPage.dataset.feedThreadItemKey || ''
                    });
                }, 70);
                return;
            }
            threadPage.classList.add('feed-thread-page-compacting');
            window.setTimeout(() => {
                if (window.history.length > 1) {
                    window.history.back();
                } else {
                    window.location.href = 'feed.php';
                }
            }, 70);
        }

        threadPage.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' && event.button !== 0) return;
            if (isSwipeIgnored(event.target)) return;

            swipe.active = true;
            swipe.pointerId = event.pointerId;
            swipe.startX = event.clientX;
            swipe.startY = event.clientY;
            swipe.lastX = event.clientX;
            swipe.dragging = false;
            threadPage.setPointerCapture?.(event.pointerId);
        });

        threadPage.addEventListener('pointermove', (event) => {
            if (!swipe.active || event.pointerId !== swipe.pointerId) return;

            const deltaX = event.clientX - swipe.startX;
            const deltaY = event.clientY - swipe.startY;

            if (!swipe.dragging) {
                if (Math.abs(deltaY) > 18 && Math.abs(deltaY) > Math.abs(deltaX)) {
                    swipe.active = false;
                    return;
                }
                if (deltaX < 12 || Math.abs(deltaX) < Math.abs(deltaY) * 1.15) {
                    return;
                }
                swipe.dragging = true;
                threadPage.classList.add('is-swipe-dragging');
            }

            const dragX = Math.max(0, deltaX);
            const dragTarget = overlayContent || threadPage;
            swipe.lastX = event.clientX;
            overlay?.classList.add('is-swipe-dragging');
            dragTarget.style.transform = `translateX(${dragX}px) scale(${Math.max(0.96, 1 - dragX / 2600)})`;
            dragTarget.style.opacity = String(Math.max(0.35, 1 - dragX / 520));
        });

        function finishSwipe(event) {
            if (!swipe.active || event.pointerId !== swipe.pointerId) return;

            const deltaX = event.clientX - swipe.startX;
            const shouldDismiss = swipe.dragging && deltaX > Math.min(150, window.innerWidth * 0.28);
            swipe.active = false;
            threadPage.classList.remove('is-swipe-dragging');
            const dragTarget = overlayContent || threadPage;
            overlay?.classList.remove('is-swipe-dragging');

            if (shouldDismiss) {
                dismissThread();
            } else {
                dragTarget.style.transform = '';
                dragTarget.style.opacity = '';
            }

            swipe.dragging = false;
        }

        threadPage.addEventListener('pointerup', finishSwipe);
        threadPage.addEventListener('pointercancel', finishSwipe);
    }

    root.querySelectorAll('[data-reply-toggle]').forEach((button) => {
        if (button.dataset.replyToggleReady === 'true') return;
        button.dataset.replyToggleReady = 'true';
        button.addEventListener('click', () => {
            const form = document.getElementById(button.getAttribute('aria-controls'));
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!isExpanded));
            button.textContent = isExpanded ? 'Reply' : 'Hide Reply';
            if (form) {
                form.hidden = isExpanded;
                if (!isExpanded) form.querySelector('textarea')?.focus();
            }
        });
    });
    root.querySelectorAll('[data-reply-cancel]').forEach((button) => {
        if (button.dataset.replyCancelReady === 'true') return;
        button.dataset.replyCancelReady = 'true';
        button.addEventListener('click', () => {
            const form = button.closest('.feed-reply-form');
            const toggle = form ? document.querySelector(`[aria-controls="${form.id}"]`) : null;
            if (form) form.hidden = true;
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
                toggle.textContent = 'Reply';
            }
        });
    });

    root.querySelectorAll('.feed-comment-form').forEach((form) => {
        if (form.dataset.shellCommentReady === 'true') return;
        form.dataset.shellCommentReady = 'true';
        form.addEventListener('submit', (event) => {
            const isOverlay = Boolean(form.closest('[data-feed-thread-overlay]'));
            if ((!isOverlay && typeof window.CraftCrawlNavigateUserShell !== 'function') || form.dataset.shellSubmitting === 'true') {
                return;
            }

            event.preventDefault();
            form.dataset.shellSubmitting = 'true';
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
            });

            fetch(form.action || window.location.href, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'CraftCrawlShell' }
            })
                .then((response) => {
                    if (!response.ok) throw new Error('Comment could not be posted.');
                    const nextUrl = response.url || window.location.href;
                    if (isOverlay && typeof window.CraftCrawlRefreshFeedThreadOverlay === 'function') {
                        return window.CraftCrawlRefreshFeedThreadOverlay(nextUrl);
                    }
                    return window.CraftCrawlNavigateUserShell(nextUrl, {
                        replace: true,
                        noStore: true
                    }).then((handled) => {
                        if (!handled) window.location.href = nextUrl;
                    });
                })
                .catch(() => {
                    HTMLFormElement.prototype.submit.call(form);
                })
                .finally(() => {
                    form.dataset.shellSubmitting = 'false';
                    form.querySelectorAll('button[type="submit"]').forEach((button) => {
                        button.disabled = false;
                    });
                });
        });
    });

    focusNotificationTargetWhenVisible(notificationFocusTarget(root));
};
window.CraftCrawlInitFeedThread();
