window.CraftCrawlInitPortalEvents = function (root = document) {
    const feedContainer = root.querySelector('#events-feed');
    const likedEventsOnly = root.querySelector('#liked-events-only');
    let eventDetailOverlay = null;
    let eventDetailOverlayContent = null;

    if (!feedContainer || feedContainer.dataset.portalEventsReady === 'true') {
        return false;
    }
    feedContainer.dataset.portalEventsReady = 'true';

    function getAllEvents(likedOnly = false) {
        return $.ajax({
            url: likedOnly ? '../mapbox/get_events.php?liked=1' : '../mapbox/get_events.php',
            dataType: 'json',
            success: function (eventData) {
                renderEventsFeed(eventData);
            },
            error: function (error) {
                console.log(error);
            }
        });
    }

    function isEventsTabActive() {
        return !feedContainer.closest('[data-user-tab-panel]')?.hidden;
    }

    function refreshVisibleEvents() {
        if (isEventsTabActive()) {
            return getAllEvents(Boolean(likedEventsOnly?.checked));
        }

        return Promise.resolve();
    }

    window.CraftCrawlRefreshPortalEvents = refreshVisibleEvents;

    function renderEventsFeed(events) {
        if (!events.length) {
            removeEventStickyDayHeader();
            feedContainer.innerHTML = '<p>No upcoming events yet.</p>';
            return;
        }

        let currentDateKey = '';

        const eventsMarkup = events.map((event) => {
            const eventDate = new Date(`${event.date}T${event.startTime}`);
            const dayLabel = formatEventDayHeader(eventDate);
            const endTime = event.endTime ? ` - ${formatEventTime(event.endTime)}` : '';
            const eventUrl = `../event_details.php?id=${encodeURIComponent(event.id)}&date=${encodeURIComponent(event.date)}`;
            const commentsUrl = `feed_post.php?item=${encodeURIComponent(event.itemKey || `event:${event.id}:${event.date}`)}`;
            const eventName = escapeHtml(event.name);
            const businessName = escapeHtml(event.businessName);
            const city = escapeHtml(event.city);
            const state = escapeHtml(event.state);
            const commentCount = Number(event.commentCount || 0);
            const commentLabel = commentCount > 0 ? `${commentCount} ${commentCount === 1 ? 'Comment' : 'Comments'}` : 'Comments';
            const description = event.description ? escapeHtml(event.description) : '';
            const coverPhoto = event.coverPhotoUrl
                ? `<img class="event-feed-cover" src="${event.coverPhotoUrl}" alt="">`
                : '';
            const dayHeader = event.date !== currentDateKey
                ? `<div class="event-feed-day-header" data-event-day-header data-date-key="${escapeHtml(event.date)}" data-date-label="${escapeHtml(dayLabel)}"><span>${escapeHtml(dayLabel)}</span></div>`
                : '';
            currentDateKey = event.date;

            return `
                ${dayHeader}
                <article class="event-feed-item ${event.coverPhotoUrl ? 'event-feed-item-with-cover' : ''}" data-event-feed-item data-event-id="${event.id}" data-occurrence-date="${escapeHtml(event.date)}" data-event-detail-url="${eventUrl}" role="link" tabindex="0">
                    ${coverPhoto}
                    <div class="event-feed-details">
                        <h3>${eventName}</h3>
                        <p>${formatEventTime(event.startTime)}${endTime} &middot; ${businessName}</p>
                        <p>${formatBusinessType(event.businessType)} &middot; ${city}, ${state}</p>
                        ${description ? `<p>${description}</p>` : ''}
                    </div>
                    <div class="event-feed-actions">
                        <a href="${eventUrl}" data-event-detail-link>View event</a>
                        <a href="../business_details.php?id=${event.businessId}">View business</a>
                        <a class="event-comments-link" href="${commentsUrl}">${commentLabel}</a>
                        <button
                            type="button"
                            class="event-want-button ${event.isWantToGo ? 'is-active' : ''}"
                            data-event-want
                            data-event-id="${event.id}"
                            data-occurrence-date="${escapeHtml(event.date)}"
                            data-is-saved="${event.isWantToGo ? '1' : '0'}"
                        >
                            📍 Want to Go ${Number(event.wantToGoCount || 0)}
                        </button>
                    </div>
                </article>
            `;
        }).join('');

        feedContainer.innerHTML = eventsMarkup;
        setupEventStickyDayHeader(feedContainer);

        feedContainer.querySelectorAll('[data-event-want]').forEach((button) => {
            button.addEventListener('click', () => {
                const formData = new FormData();
                formData.append('csrf_token', window.CRAFTCRAWL_CSRF_TOKEN || '');
                formData.append('event_id', button.dataset.eventId);
                formData.append('occurrence_date', button.dataset.occurrenceDate);
                formData.append('is_saved', button.dataset.isSaved || '0');
                button.disabled = true;
                button.classList.add('is-loading');

                fetch('../user/event_want_to_go_toggle.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (!data.ok) {
                            return;
                        }

                        button.dataset.isSaved = data.is_saved ? '1' : '0';
                        button.classList.toggle('is-active', Boolean(data.is_saved));
                        button.textContent = `📍 Want to Go ${Number(data.count || 0)}`;
                        if (data.xp_reward && window.craftcrawlShowXpReward) {
                            window.craftcrawlShowXpReward(data.xp_reward);
                        }
                        window.dispatchEvent(new CustomEvent('craftcrawl:event-want-updated'));
                    })
                    .finally(() => {
                        button.disabled = false;
                        button.classList.remove('is-loading');
                    });
            });
        });

        feedContainer.querySelectorAll('[data-event-detail-link]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                openEventDetailOverlay(link.href, link.closest('[data-event-feed-item]'));
            });
        });

        feedContainer.querySelectorAll('[data-event-feed-item]').forEach((item) => {
            item.addEventListener('click', (event) => {
                if (event.target.closest('a, button, input, textarea, select, label, [role="button"]')) {
                    return;
                }
                openEventDetailOverlay(item.dataset.eventDetailUrl, item);
            });
            item.addEventListener('keydown', (event) => {
                if (event.target !== item || !['Enter', ' '].includes(event.key)) {
                    return;
                }
                event.preventDefault();
                openEventDetailOverlay(item.dataset.eventDetailUrl, item);
            });
        });
    }

    function normalizeEventDetailUrl(url) {
        return new URL(url, window.location.href).href;
    }

    async function fetchEventDetailPage(url) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'CraftCrawlShell' }
        });
        if (!response.ok) throw new Error('Event could not be loaded.');
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const page = doc.querySelector('.event-detail-page');
        if (!page) throw new Error('Event content was missing.');
        return page;
    }

    function ensureEventDetailOverlay() {
        if (eventDetailOverlay) {
            return eventDetailOverlay;
        }

        eventDetailOverlay = document.createElement('div');
        eventDetailOverlay.className = 'event-detail-overlay';
        eventDetailOverlay.setAttribute('data-event-detail-overlay', '');
        eventDetailOverlay.hidden = true;
        eventDetailOverlay.innerHTML = '<div class="event-detail-overlay-scrim" data-event-detail-overlay-close></div><div class="event-detail-edge-swipe" data-event-detail-edge-swipe aria-hidden="true"></div><div class="event-detail-overlay-content" data-event-detail-overlay-content></div>';
        document.body.appendChild(eventDetailOverlay);
        eventDetailOverlayContent = eventDetailOverlay.querySelector('[data-event-detail-overlay-content]');

        eventDetailOverlay.addEventListener('click', (event) => {
            if (event.target.closest('[data-event-detail-overlay-close]')) {
                closeEventDetailOverlay({ useHistory: true });
            }
        });

        return eventDetailOverlay;
    }

    function absolutizeEventDetailUrls(page, sourceUrl) {
        page.querySelectorAll('[href]').forEach((element) => {
            element.setAttribute('href', new URL(element.getAttribute('href'), sourceUrl).href);
        });
        page.querySelectorAll('[src]').forEach((element) => {
            element.setAttribute('src', new URL(element.getAttribute('src'), sourceUrl).href);
        });
        page.querySelectorAll('form[action]').forEach((form) => {
            form.setAttribute('action', new URL(form.getAttribute('action'), sourceUrl).href);
        });
    }

    function setEventDetailOverlayContent(page, sourceUrl) {
        ensureEventDetailOverlay();
        eventDetailOverlayContent.style.transform = '';
        eventDetailOverlayContent.style.opacity = '';
        absolutizeEventDetailUrls(page, sourceUrl);
        page.classList.add('event-detail-page-entering');
        eventDetailOverlayContent.replaceChildren(page);
        window.setTimeout(() => page.classList.remove('event-detail-page-entering'), 420);
        window.CraftCrawlInitEventDetail?.(eventDetailOverlay);
    }

    function closeEventDetailOverlay(options = {}) {
        if (!eventDetailOverlay || eventDetailOverlay.hidden) {
            return false;
        }
        if (eventDetailOverlay.classList.contains('is-closing')) {
            return true;
        }

        eventDetailOverlay.classList.add('is-closing');
        document.body.classList.remove('event-detail-overlay-open');

        window.setTimeout(() => {
            eventDetailOverlay.hidden = true;
            eventDetailOverlay.classList.remove('is-open', 'is-closing', 'is-swipe-dragging', 'is-swipe-dismissing');
            if (eventDetailOverlayContent) {
                eventDetailOverlayContent.style.transform = '';
                eventDetailOverlayContent.style.opacity = '';
            }
            eventDetailOverlayContent?.replaceChildren();
            refreshVisibleEvents();
        }, 110);

        if (options.useHistory && history.state?.craftcrawlEventDetailOverlay) {
            history.back();
        }

        return true;
    }

    async function openEventDetailOverlay(url, eventItem = null, options = {}) {
        const targetUrl = normalizeEventDetailUrl(url);
        eventItem?.classList.add('is-opening-event');

        try {
            ensureEventDetailOverlay();
            eventDetailOverlay.classList.remove('is-closing', 'is-swipe-dragging', 'is-swipe-dismissing');
            eventDetailOverlayContent.style.transform = '';
            eventDetailOverlayContent.style.opacity = '';
            const page = await fetchEventDetailPage(targetUrl);
            setEventDetailOverlayContent(page, targetUrl);
            eventDetailOverlay.hidden = false;
            eventDetailOverlay.classList.add('is-open');
            document.body.classList.add('event-detail-overlay-open');
            eventItem?.classList.remove('is-opening-event');

            if (options.updateHistory !== false) {
                history.pushState({ craftcrawlEventDetailOverlay: true }, '', targetUrl);
            }
            return true;
        } catch (_) {
            eventItem?.classList.remove('is-opening-event');
            window.location.href = targetUrl;
            return true;
        }
    }

    window.CraftCrawlOpenEventDetailOverlay = openEventDetailOverlay;
    window.CraftCrawlCloseEventDetailOverlay = closeEventDetailOverlay;

    window.addEventListener('popstate', () => {
        if (eventDetailOverlay && !eventDetailOverlay.hidden && !history.state?.craftcrawlEventDetailOverlay) {
            closeEventDetailOverlay({ useHistory: false });
        }
    });

    function setupEventStickyDayHeader(container) {
        removeEventStickyDayHeader();

        const stickyHeader = document.createElement('div');
        stickyHeader.className = 'event-feed-floating-day-header';
        stickyHeader.setAttribute('aria-hidden', 'true');
        stickyHeader.innerHTML = '<span></span>';
        document.body.appendChild(stickyHeader);

        const label = stickyHeader.querySelector('span');
        const state = container.eventStickyDayState || {
            activeDate: '',
            ticking: false
        };
        state.header = stickyHeader;
        state.label = label;
        state.activeDate = '';
        container.eventStickyDayState = state;

        function updateStickyHeader() {
            state.ticking = false;
            const headers = Array.from(container.querySelectorAll('[data-event-day-header]'));
            if (!headers.length || !state.header || !state.label) {
                return;
            }

            const feedRect = container.getBoundingClientRect();
            const shouldShow = window.matchMedia('(max-width: 760px)').matches
                && feedRect.top <= 68
                && feedRect.bottom > 96;
            state.header.classList.toggle('is-visible', shouldShow);

            const threshold = 74;
            let activeHeader = headers[0];
            headers.forEach((header) => {
                if (header.getBoundingClientRect().top <= threshold) {
                    activeHeader = header;
                }
            });

            const nextDate = activeHeader.dataset.dateKey || '';
            if (nextDate && nextDate !== state.activeDate) {
                state.activeDate = nextDate;
                state.label.textContent = activeHeader.dataset.dateLabel || '';
                state.header.classList.remove('is-changing');
                window.requestAnimationFrame(() => state.header?.classList.add('is-changing'));
            }
        }

        function requestUpdate() {
            if (state.ticking) {
                return;
            }
            state.ticking = true;
            window.requestAnimationFrame(updateStickyHeader);
        }

        if (container.dataset.eventStickyDayReady !== 'true') {
            container.dataset.eventStickyDayReady = 'true';
            window.addEventListener('scroll', requestUpdate, { passive: true });
            window.addEventListener('resize', requestUpdate);
            document.addEventListener('scroll', requestUpdate, { capture: true, passive: true });
        }

        updateStickyHeader();
        window.setTimeout(updateStickyHeader, 80);
    }

    function removeEventStickyDayHeader() {
        document.querySelectorAll('.event-feed-floating-day-header').forEach((header) => header.remove());
    }

    function formatBusinessType(type) {
        const labels = {
            brewery: 'Brewery',
            winery: 'Winery',
            cidery: 'Cidery',
            distillery: 'Distillery',
            meadery: 'Meadery',
            bar: 'Bar',
            social_club: 'Social Club'
        };

        return labels[type] || 'Business';
    }

    function formatEventTime(time) {
        const date = new Date(`2000-01-01T${time}`);

        return date.toLocaleTimeString(undefined, {
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    function formatEventDayHeader(date) {
        const monthDay = date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        const weekday = date.toLocaleDateString(undefined, { weekday: 'short' });
        return `${monthDay}, ${weekday}`;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    likedEventsOnly?.addEventListener('change', () => {
        getAllEvents(likedEventsOnly.checked);
    });

    window.addEventListener('craftcrawl:user-tab-changed', (event) => {
        if (event.detail?.tab === 'events') {
            refreshVisibleEvents();
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshVisibleEvents();
        }
    });

    getAllEvents(Boolean(likedEventsOnly?.checked));
    return true;
};

window.CraftCrawlInitEventDetail = function (root = document) {
    const detailPage = root.querySelector('.event-detail-page');

    if (!detailPage || detailPage.dataset.eventDetailReady === 'true') {
        return false;
    }
    detailPage.dataset.eventDetailReady = 'true';

    detailPage.querySelectorAll('[data-event-detail-want]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const button = form.querySelector('button');
            const savedInput = form.querySelector('[name="is_saved"]');
            button.disabled = true;
            button.classList.add('is-loading');

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin'
            })
                .then((response) => response.json())
                .then((data) => {
                    if (!data.ok) {
                        return;
                    }
                    savedInput.value = data.is_saved ? '1' : '0';
                    button.classList.toggle('is-active', Boolean(data.is_saved));
                    button.textContent = `📍 Want to Go ${Number(data.count || 0)}`;
                    if (data.xp_reward && window.craftcrawlShowXpReward) {
                        window.craftcrawlShowXpReward(data.xp_reward);
                    }
                    window.dispatchEvent(new CustomEvent('craftcrawl:event-want-updated'));
                })
                .finally(() => {
                    button.disabled = false;
                    button.classList.remove('is-loading');
                });
        });
    });

    const overlay = detailPage.closest('[data-event-detail-overlay]');
    const overlayContent = overlay?.querySelector('[data-event-detail-overlay-content]');
    const edgeSwipe = overlay?.querySelector('[data-event-detail-edge-swipe]');
    const swipeSurface = overlayContent || detailPage;
    swipeSurface._craftcrawlEventSwipeAbort?.abort();
    const swipeAbort = new AbortController();
    swipeSurface._craftcrawlEventSwipeAbort = swipeAbort;

    function dismissEventDetail() {
        if (overlay && typeof window.CraftCrawlCloseEventDetailOverlay === 'function') {
            overlay.classList.add('is-swipe-dismissing');
            window.setTimeout(() => {
                window.CraftCrawlCloseEventDetailOverlay({ useHistory: true });
            }, 35);
            return;
        }

        detailPage.classList.add('event-detail-page-compacting');
        window.setTimeout(() => {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = 'user/events.php';
            }
        }, 35);
    }

    detailPage.querySelectorAll('[data-back-link]').forEach((link) => {
        if (link.dataset.eventDetailBackReady === 'true') return;
        link.dataset.eventDetailBackReady = 'true';
        link.addEventListener('click', (event) => {
            if (overlay && typeof window.CraftCrawlCloseEventDetailOverlay === 'function') {
                event.preventDefault();
                window.CraftCrawlCloseEventDetailOverlay({ useHistory: true });
            }
        }, { capture: true });
    });

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
        return Boolean(target.closest('a, button, input, textarea, select, label, [role="button"], form'));
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
            detailPage.classList.add('is-swipe-dragging');
            overlay?.classList.add('is-swipe-dragging');
        }

        if (event?.cancelable) {
            event.preventDefault();
        }
        const dragX = Math.max(0, deltaX);
        const dragTarget = overlayContent || detailPage;
        dragTarget.style.transform = `translateX(${dragX}px) scale(${Math.max(0.96, 1 - dragX / 2600)})`;
        dragTarget.style.opacity = String(Math.max(0.35, 1 - dragX / 520));
    }

    function lockOverlayScrollForSwipe() {
        if (!overlayContent || swipe.scrollLocked) return;
        swipe.scrollLocked = true;
        overlayContent.dataset.eventSwipeScrollTop = String(overlayContent.scrollTop);
        overlayContent.classList.add('is-swipe-scroll-locked');
    }

    function unlockOverlayScrollForSwipe() {
        if (!overlayContent || !swipe.scrollLocked) return;
        const scrollTop = Number(overlayContent.dataset.eventSwipeScrollTop || overlayContent.scrollTop || 0);
        swipe.scrollLocked = false;
        overlayContent.classList.remove('is-swipe-scroll-locked');
        overlayContent.scrollTop = scrollTop;
        delete overlayContent.dataset.eventSwipeScrollTop;
    }

    function finishSwipeAt(clientX) {
        if (!swipe.active) return;

        const deltaX = clientX - swipe.startX;
        const dismissDistance = Math.min(110, Math.max(82, window.innerWidth * 0.18));
        const shouldDismiss = swipe.dragging && deltaX > dismissDistance;
        swipe.active = false;
        detailPage.classList.remove('is-swipe-dragging');
        overlay?.classList.remove('is-swipe-dragging');
        const dragTarget = overlayContent || detailPage;

        if (shouldDismiss) {
            dismissEventDetail();
        } else {
            dragTarget.style.transform = '';
            dragTarget.style.opacity = '';
            unlockOverlayScrollForSwipe();
        }

        swipe.dragging = false;
    }

    const swipeSurfaces = Array.from(new Set([swipeSurface, edgeSwipe].filter(Boolean)));
    swipeSurfaces.forEach((surface) => {
        surface._craftcrawlEventSwipeAbort = swipeAbort;
    });

    swipeSurfaces.forEach((surface) => surface.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        if (isSwipeIgnored(event.target)) return;
        if (surface === edgeSwipe) {
            lockOverlayScrollForSwipe();
        }

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
        if (surface === edgeSwipe) {
            lockOverlayScrollForSwipe();
        }
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

    return true;
};

window.CraftCrawlInitPortalEvents();
window.CraftCrawlInitEventDetail();
