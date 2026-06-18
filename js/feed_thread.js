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

    function updateThreadScrollability() {
        const page = root.querySelector('.feed-thread-page');
        const overlay = page?.closest('[data-feed-thread-overlay]');
        const overlayContent = overlay?.querySelector('[data-feed-thread-overlay-content]');
        if (!overlayContent) return;

        const canScroll = overlayContent.scrollHeight > overlayContent.clientHeight + 2;
        overlayContent.classList.toggle('is-not-scrollable', !canScroll);
    }

    function scheduleThreadScrollabilityUpdate() {
        updateThreadScrollability();
        window.requestAnimationFrame(updateThreadScrollability);
        window.setTimeout(updateThreadScrollability, 220);
    }

    if (threadPage && threadPage.dataset.swipeDismissReady !== 'true') {
        threadPage.dataset.swipeDismissReady = 'true';
        const overlay = threadPage.closest('[data-feed-thread-overlay]');
        const overlayContent = overlay?.querySelector('[data-feed-thread-overlay-content]');
        const swipeSurface = overlayContent || threadPage;
        swipeSurface._craftcrawlFeedSwipeAbort?.abort();
        const swipeAbort = new AbortController();
        swipeSurface._craftcrawlFeedSwipeAbort = swipeAbort;

        scheduleThreadScrollabilityUpdate();
        window.setTimeout(updateThreadScrollability, 350);
        window.addEventListener('resize', scheduleThreadScrollabilityUpdate, { signal: swipeAbort.signal });
        window.visualViewport?.addEventListener('resize', scheduleThreadScrollabilityUpdate, { signal: swipeAbort.signal });

        const swipe = {
            active: false,
            pointerId: null,
            startX: 0,
            startY: 0,
            lastX: 0,
            dragging: false,
            scrollLocked: false
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
            const fallbackHref = threadPage.querySelector('[data-back-link]')?.href || 'feed.php';
            if (overlay && typeof window.CraftCrawlCloseFeedThreadOverlay === 'function') {
                overlay.classList.add('is-swipe-dismissing');
                window.setTimeout(() => {
                    window.CraftCrawlCloseFeedThreadOverlay({
                        useHistory: true,
                        returnItemKey: threadPage.dataset.feedThreadItemKey || ''
                    });
                }, 35);
                return;
            }
            threadPage.classList.add('feed-thread-page-compacting');
            window.setTimeout(() => {
                if (window.history.length > 1) {
                    window.history.back();
                } else {
                    window.location.href = fallbackHref;
                }
            }, 35);
        }

        function moveSwipe(clientX, clientY, event = null) {
            if (!swipe.active) return;

            const deltaX = clientX - swipe.startX;
            const deltaY = clientY - swipe.startY;
            const absY = Math.abs(deltaY);
            swipe.lastX = clientX;

            if (deltaX > 6 && event?.cancelable) {
                event.preventDefault();
            }

            if (!swipe.dragging) {
                if (deltaX < 6 && absY > 64) {
                    swipe.active = false;
                    return;
                }
                if (deltaX < 8) {
                    return;
                }
                swipe.dragging = true;
                threadPage.classList.add('is-swipe-dragging');
                overlay?.classList.add('is-swipe-dragging');
            }

            if (event?.cancelable) {
                event.preventDefault();
            }
            const dragX = Math.max(0, deltaX);
            const dragTarget = overlayContent || threadPage;
            dragTarget.style.transform = `translateX(${dragX}px) scale(${Math.max(0.96, 1 - dragX / 2600)})`;
            dragTarget.style.opacity = String(Math.max(0.35, 1 - dragX / 520));
        }

        function lockOverlayScrollForSwipe() {
            if (!overlayContent || swipe.scrollLocked) return;
            swipe.scrollLocked = true;
            overlayContent.dataset.feedSwipeScrollTop = String(overlayContent.scrollTop);
            overlayContent.classList.add('is-swipe-scroll-locked');
        }

        function unlockOverlayScrollForSwipe() {
            if (!overlayContent || !swipe.scrollLocked) return;
            const scrollTop = Number(overlayContent.dataset.feedSwipeScrollTop || overlayContent.scrollTop || 0);
            swipe.scrollLocked = false;
            overlayContent.classList.remove('is-swipe-scroll-locked');
            overlayContent.scrollTop = scrollTop;
            delete overlayContent.dataset.feedSwipeScrollTop;
        }

        function finishSwipeAt(clientX) {
            if (!swipe.active) return;

            const deltaX = clientX - swipe.startX;
            const dismissDistance = Math.min(110, Math.max(82, window.innerWidth * 0.18));
            const shouldDismiss = swipe.dragging && deltaX > dismissDistance;
            swipe.active = false;
            threadPage.classList.remove('is-swipe-dragging');
            overlay?.classList.remove('is-swipe-dragging');
            const dragTarget = overlayContent || threadPage;

            if (shouldDismiss) {
                dismissThread();
            } else {
                dragTarget.style.transform = '';
                dragTarget.style.opacity = '';
                unlockOverlayScrollForSwipe();
            }

            swipe.dragging = false;
        }

        const swipeSurfaces = [swipeSurface];
        swipeSurfaces.forEach((surface) => {
            surface._craftcrawlFeedSwipeAbort = swipeAbort;
        });

        swipeSurfaces.forEach((surface) => surface.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' && event.button !== 0) return;
            if (isSwipeIgnored(event.target)) return;

            swipe.active = true;
            swipe.pointerId = event.pointerId;
            swipe.startX = event.clientX;
            swipe.startY = event.clientY;
            swipe.lastX = event.clientX;
            swipe.dragging = false;
            surface.setPointerCapture?.(event.pointerId);
        }, { signal: swipeAbort.signal }));

        swipeSurfaces.forEach((surface) => surface.addEventListener('pointermove', (event) => {
            if (!swipe.active || event.pointerId !== swipe.pointerId) return;
            moveSwipe(event.clientX, event.clientY, event);
        }, { signal: swipeAbort.signal }));

        function finishSwipe(event) {
            if (!swipe.active || event.pointerId !== swipe.pointerId) return;

            const finishX = typeof event.clientX === 'number' && event.clientX !== 0 ? event.clientX : swipe.lastX;
            finishSwipeAt(finishX);
        }

        swipeSurfaces.forEach((surface) => surface.addEventListener('pointerup', finishSwipe, { signal: swipeAbort.signal }));
        swipeSurfaces.forEach((surface) => surface.addEventListener('pointercancel', finishSwipe, { signal: swipeAbort.signal }));
        swipeSurfaces.forEach((surface) => surface.addEventListener('touchstart', (event) => {
            if (swipe.active || event.touches.length !== 1 || isSwipeIgnored(event.target)) return;
            const touch = event.touches[0];
            swipe.active = true;
            swipe.pointerId = null;
            swipe.startX = touch.clientX;
            swipe.startY = touch.clientY;
            swipe.lastX = touch.clientX;
            swipe.dragging = false;
        }, { passive: false, signal: swipeAbort.signal }));
        swipeSurfaces.forEach((surface) => surface.addEventListener('touchmove', (event) => {
            if (!swipe.active || swipe.pointerId !== null || event.touches.length !== 1) return;
            const touch = event.touches[0];
            moveSwipe(touch.clientX, touch.clientY, event);
        }, { passive: false, signal: swipeAbort.signal }));
        swipeSurfaces.forEach((surface) => surface.addEventListener('touchend', () => {
            if (!swipe.active || swipe.pointerId !== null) return;
            finishSwipeAt(swipe.lastX);
        }, { signal: swipeAbort.signal }));
        swipeSurfaces.forEach((surface) => surface.addEventListener('touchcancel', () => {
            if (!swipe.active || swipe.pointerId !== null) return;
            finishSwipeAt(swipe.lastX);
        }, { signal: swipeAbort.signal }));
    }

    const composeForm = root.querySelector('#feed-compose-form');
    const composeParentInput = root.querySelector('[data-compose-parent-id]');
    const composeContext = root.querySelector('[data-compose-context]');
    const composePanel = composeForm?.closest('.feed-compose-panel');
    const composeReference = root.querySelector('[data-compose-reference]');
    const composeLabel = root.querySelector('[data-compose-label]');
    const composeSubmit = root.querySelector('[data-compose-submit]');
    const composeHome = composeForm
        ? { parent: composeForm.parentNode, nextSibling: composeForm.nextSibling }
        : null;
    let activeComposeTarget = null;
    let composerScrollRestore = null;
    let composerTargetSnapshot = null;

    function updateComposerSpace() {
        if (!composeForm || composeForm.hidden || !threadPage) return 0;

        const composerHeight = Math.ceil(composeForm.getBoundingClientRect().height || 0);
        const offset = composerHeight + 28;
        threadPage.style.setProperty('--feed-compose-offset', `${offset}px`);
        threadPage.classList.add('is-compose-open');
        scheduleThreadScrollabilityUpdate();
        return offset;
    }

    function clearComposeTarget() {
        activeComposeTarget?.classList.remove('is-compose-target');
        activeComposeTarget = null;
    }

    function closeComposer() {
        const targetSnapshot = composerTargetSnapshot;
        if (composeForm?.contains(document.activeElement)) {
            document.activeElement.blur();
        }
        if (composeForm) composeForm.hidden = true;
        if (composeForm && composeHome?.parent && composeForm.parentNode !== composeHome.parent) {
            composeHome.parent.insertBefore(composeForm, composeHome.nextSibling);
        }
        composePanel?.classList.remove('is-compose-panel-open');
        composeReference?.replaceChildren();
        document.body.classList.remove('feed-comment-composer-open');
        threadPage?.classList.remove('is-compose-open');
        threadPage?.style.removeProperty('--feed-compose-offset');
        clearComposeTarget();
        if (targetSnapshot?.element?.isConnected && targetSnapshot.html) {
            targetSnapshot.element.innerHTML = targetSnapshot.html;
            window.CraftCrawlInitFeedThread?.(root);
        }
        composerTargetSnapshot = null;
        if (composerScrollRestore) {
            const { container, top, windowY } = composerScrollRestore;
            const restoreScroll = () => {
                if (container) {
                    container.scrollTop = targetSnapshot?.element?.offsetTop ?? top;
                } else {
                    const targetTop = targetSnapshot?.element
                        ? targetSnapshot.element.getBoundingClientRect().top + window.scrollY - 12
                        : windowY;
                    window.scrollTo(window.scrollX, Math.max(0, targetTop));
                }
            };
            window.requestAnimationFrame(restoreScroll);
            window.setTimeout(restoreScroll, 180);
            composerScrollRestore = null;
        }
        scheduleThreadScrollabilityUpdate();
    }

    function directChild(target, selector) {
        if (!target) return null;
        return Array.from(target.children || []).find((child) => child.matches?.(selector)) || null;
    }

    function normalizeText(text) {
        return String(text || '').replace(/\s+/g, ' ').trim();
    }

    function buildComposeReference(target, isReply, referenceData = null) {
        const source = isReply
            ? target
            : target?.matches?.('.feed-thread-post')
                ? target
                : target?.querySelector?.('.feed-thread-post') || target;

        if (referenceData && (referenceData.title || referenceData.meta || referenceData.body)) {
            return {
                avatar: directChild(source, '.feed-avatar-link, .user-avatar, .friends-feed-icon')?.cloneNode(true) || null,
                title: referenceData.title,
                meta: referenceData.meta,
                body: referenceData.body
            };
        }

        if (!source) return null;

        const content = directChild(source, 'div');
        const title = isReply
            ? normalizeText(directChild(source, 'div')?.querySelector('strong')?.textContent)
            : normalizeText(content?.querySelector('strong')?.textContent);
        const meta = isReply
            ? normalizeText(directChild(source, 'div')?.querySelector('span')?.textContent)
            : normalizeText(content?.querySelector('p:not(.feed-user-post-body)')?.textContent);
        const body = isReply
            ? normalizeText(directChild(source, 'p')?.textContent)
            : normalizeText(content?.querySelector('.feed-user-post-body')?.textContent);
        const avatar = directChild(source, '.feed-avatar-link, .user-avatar, .friends-feed-icon')?.cloneNode(true);

        return { avatar, title, meta, body };
    }

    function updateComposeReference(target, isReply, referenceData = null) {
        if (!composeReference) return;
        composeReference.replaceChildren();

        const reference = buildComposeReference(target, isReply, referenceData);
        if (!reference || (!reference.title && !reference.meta && !reference.body && !reference.avatar)) {
            return;
        }

        const item = document.createElement('article');
        item.className = 'feed-compose-reference-item';

        const avatarWrap = document.createElement('div');
        avatarWrap.className = 'feed-compose-reference-avatar';
        if (reference.avatar) {
            reference.avatar.querySelectorAll?.('a, button').forEach((element) => element.replaceWith(...element.childNodes));
            avatarWrap.appendChild(reference.avatar);
        }

        const textWrap = document.createElement('div');
        textWrap.className = 'feed-compose-reference-copy';
        if (reference.title) {
            const title = document.createElement('strong');
            title.textContent = reference.title;
            textWrap.appendChild(title);
        }
        if (reference.meta) {
            const meta = document.createElement('span');
            meta.textContent = reference.meta;
            textWrap.appendChild(meta);
        }
        if (reference.body) {
            const body = document.createElement('p');
            body.textContent = reference.body;
            textWrap.appendChild(body);
        }

        if (reference.avatar) {
            item.appendChild(avatarWrap);
        } else {
            item.classList.add('feed-compose-reference-item-text-only');
        }
        item.appendChild(textWrap);
        composeReference.appendChild(item);
    }

    function openComposer(options = {}) {
        if (!composeForm) return;

        const parentId = options.parentId || '';
        const label = options.label || 'post';
        const targetSelector = options.target || '[data-compose-target]';
        const trigger = options.trigger || null;
        const referenceData = options.reference || null;
        const target = !parentId && trigger
            ? trigger.closest('[data-compose-target]')
            : targetSelector.startsWith('#') || targetSelector.startsWith('[')
            ? root.querySelector(targetSelector)
            : root.querySelector(`#${CSS.escape(targetSelector)}`);

        clearComposeTarget();
        activeComposeTarget = target || null;
        activeComposeTarget?.classList.add('is-compose-target');
        composerTargetSnapshot = !parentId && activeComposeTarget
            ? { element: activeComposeTarget, html: activeComposeTarget.innerHTML }
            : null;

        if (composeParentInput) composeParentInput.value = parentId;
        if (composeContext) {
            composeContext.textContent = parentId ? `Replying to ${label}` : 'Commenting on this post';
        }
        if (composeLabel) {
            composeLabel.textContent = parentId ? 'Reply' : 'Comment';
        }
        if (composeSubmit) {
            composeSubmit.textContent = parentId ? 'Post Reply' : 'Post Comment';
        }
        updateComposeReference(activeComposeTarget, Boolean(parentId), referenceData);

        const scrollContainer = threadPage?.closest('[data-feed-thread-overlay-content]');
        composerScrollRestore = scrollContainer
            ? { container: scrollContainer, top: scrollContainer.scrollTop, windowY: window.scrollY }
            : { container: null, top: 0, windowY: window.scrollY };
        if (composeForm.parentNode !== document.body) {
            document.body.appendChild(composeForm);
        }
        composeForm.hidden = false;
        composePanel?.classList.add('is-compose-panel-open');
        document.body.classList.add('feed-comment-composer-open');
        updateComposerSpace();
        window.requestAnimationFrame(() => {
            const textarea = composeForm.querySelector('textarea');
            if (textarea) {
                try {
                    textarea.focus({ preventScroll: true });
                } catch (_) {
                    textarea.focus();
                }
            }
            [80, 180, 360, 700].forEach((delay) => window.setTimeout(updateComposerSpace, delay));
        });
    }

    root.querySelectorAll('[data-reply-toggle]').forEach((button) => {
        if (button.dataset.composeTriggerReady === 'true') return;
        button.dataset.composeTriggerReady = 'true';
        button.addEventListener('click', () => {
            openComposer({
                parentId: button.dataset.parentCommentId || '',
                label: button.dataset.replyLabel || 'post',
                target: button.dataset.replyTarget || '[data-compose-target]',
                reference: {
                    title: button.dataset.referenceTitle || '',
                    meta: button.dataset.referenceMeta || '',
                    body: button.dataset.referenceBody || ''
                },
                trigger: button
            });
        });
    });

    root.querySelectorAll('[data-replies-toggle]').forEach((button) => {
        if (button.dataset.repliesToggleReady === 'true') return;
        button.dataset.repliesToggleReady = 'true';
        button.addEventListener('click', () => {
            const panelId = button.getAttribute('aria-controls') || '';
            const panel = panelId ? root.querySelector(`#${CSS.escape(panelId)}`) : null;
            if (!panel) return;

            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!isExpanded));
            panel.hidden = isExpanded;
            scheduleThreadScrollabilityUpdate();
        });
    });

    root.querySelectorAll('[data-compose-cancel]').forEach((button) => {
        if (button.dataset.composeCancelReady === 'true') return;
        button.dataset.composeCancelReady = 'true';
        button.addEventListener('click', closeComposer);
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
                    if (form.classList.contains('feed-compose-form')) {
                        form.querySelector('textarea').value = '';
                        closeComposer();
                    }
                });
        });
    });

    focusNotificationTargetWhenVisible(notificationFocusTarget(root));
};
window.CraftCrawlInitFeedThread();
