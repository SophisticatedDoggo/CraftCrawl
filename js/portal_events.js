window.CraftCrawlInitPortalEvents = function (root = document) {
    const feedContainer = root.querySelector('#events-feed');
    const likedEventsOnly = root.querySelector('#liked-events-only');
    let eventDetailOverlay = null;
    let eventDetailOverlayContent = null;
    let eventStickyDayHeader = null;
    let eventStickyDayLabel = null;
    let activeStickyDate = '';
    let stickyDayUpdatePending = false;

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
        return feedContainer.isConnected
            && !document.hidden
            && !feedContainer.closest('[data-user-page-content]')?.hidden
            && !feedContainer.closest('[data-user-tab-panel]')?.hidden;
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
            const itemKey = event.itemKey || `event:${event.id}:${event.date}`;
            const eventName = escapeHtml(event.name);
            const businessName = escapeHtml(event.businessName);
            const city = escapeHtml(event.city);
            const state = escapeHtml(event.state);
            const commentCount = Number(event.commentCount || 0);
            const wantToGoCount = Number(event.wantToGoCount || 0);
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
                <div class="event-feed-entry" data-event-feed-item data-feed-item-key="${escapeHtml(itemKey)}" data-event-id="${event.id}" data-occurrence-date="${escapeHtml(event.date)}" data-event-detail-url="${eventUrl}" role="link" tabindex="0">
                    ${coverPhoto}
                    <article class="event-feed-item ${event.coverPhotoUrl ? 'event-feed-item-with-cover' : ''}">
                        <div class="event-feed-details">
                            <h3><a class="feed-event-link" href="${eventUrl}" data-event-detail-link>${eventName}</a></h3>
                            <p>${formatEventTime(event.startTime)}${endTime} &middot; <a class="feed-business-link" href="../business_details.php?id=${event.businessId}">${businessName}</a></p>
                            <p>${formatBusinessType(event.businessType)} &middot; ${city}, ${state}</p>
                            ${description ? `<p>${description}</p>` : ''}
                        </div>
                        <div class="feed-action-row event-feed-action-row">
                            <div class="feed-primary-actions">
                                <button type="button" class="feed-comments-link" data-comments-sheet-trigger data-item-key="${escapeHtml(itemKey)}" aria-label="Show comments">
                                    <span class="feed-comments-icon" aria-hidden="true"></span>
                                    ${commentCount > 0 ? `<span class="feed-comment-count">${commentCount}</span>` : ''}
                                </button>
                            </div>
                            <div class="feed-reactions event-feed-want-reaction">
                                <button
                                    type="button"
                                    class="event-want-button ${event.isWantToGo ? 'is-active' : ''}"
                                    data-event-want
                                    data-event-id="${event.id}"
                                    data-occurrence-date="${escapeHtml(event.date)}"
                                    data-is-saved="${event.isWantToGo ? '1' : '0'}"
                                    aria-label="Want to Go"
                                    aria-pressed="${event.isWantToGo ? 'true' : 'false'}"
                                >
                                    <span class="feed-reaction-icon feed-reaction-icon-pin" aria-hidden="true"></span>
                                    <span class="feed-reaction-count"${wantToGoCount > 0 ? '' : ' hidden'}>${wantToGoCount > 0 ? wantToGoCount : ''}</span>
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
            `;
        }).join('');

        feedContainer.innerHTML = eventsMarkup;
        setupEventStickyDayHeader();

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
                        button.setAttribute('aria-pressed', data.is_saved ? 'true' : 'false');
                        const count = Number(data.count || 0);
                        const countElement = button.querySelector('.feed-reaction-count');
                        if (countElement) {
                            countElement.textContent = count > 0 ? String(count) : '';
                            countElement.hidden = count < 1;
                        }
                        if (data.is_saved) {
                            button.classList.add('is-reacting');
                            window.setTimeout(() => button.classList.remove('is-reacting'), 280);
                        }
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

    function setupEventStickyDayHeader() {
        if (eventStickyDayHeader?.isConnected) {
            requestEventStickyDayUpdate();
            window.setTimeout(requestEventStickyDayUpdate, 80);
            return;
        }

        document.querySelectorAll('.event-feed-floating-day-header').forEach((header) => header.remove());

        eventStickyDayHeader = document.createElement('div');
        eventStickyDayHeader.className = 'event-feed-floating-day-header';
        eventStickyDayHeader.setAttribute('aria-hidden', 'true');
        eventStickyDayHeader.innerHTML = '<span></span>';
        document.body.appendChild(eventStickyDayHeader);
        eventStickyDayLabel = eventStickyDayHeader.querySelector('span');
        activeStickyDate = '';

        requestEventStickyDayUpdate();
        window.setTimeout(requestEventStickyDayUpdate, 80);
    }

    function hideEventStickyDayHeader() {
        if (!eventStickyDayHeader) {
            return;
        }

        eventStickyDayHeader.classList.remove('is-visible', 'is-changing');
        eventStickyDayHeader.style.setProperty('--event-feed-day-push', '0px');
    }

    function removeEventStickyDayHeader() {
        hideEventStickyDayHeader();
        document.querySelectorAll('.event-feed-floating-day-header').forEach((header) => header.remove());
        eventStickyDayHeader = null;
        eventStickyDayLabel = null;
        activeStickyDate = '';
    }

    function updateEventStickyDayHeader() {
        stickyDayUpdatePending = false;

        if (!eventStickyDayHeader || !eventStickyDayLabel) {
            return;
        }

        const headers = Array.from(feedContainer.querySelectorAll('[data-event-day-header]'));
        const isMobile = window.matchMedia('(max-width: 760px)').matches;

        if (!headers.length || !isMobile || !isEventsTabActive()) {
            hideEventStickyDayHeader();
            return;
        }

        const feedRect = feedContainer.getBoundingClientRect();
        const headerRects = headers.map((header) => header.getBoundingClientRect());
        eventStickyDayHeader.style.setProperty('--event-feed-day-height', `${headerRects[0].height}px`);
        const floatingHeaderRect = eventStickyDayHeader.getBoundingClientRect();
        const floatingHeaderHeight = floatingHeaderRect.height;
        const shouldShow = headerRects[0].top <= 0 && feedRect.bottom > 0;
        eventStickyDayHeader.classList.toggle('is-visible', shouldShow);

        if (!shouldShow) {
            hideEventStickyDayHeader();
            return;
        }

        let activeHeaderIndex = 0;
        headerRects.forEach((headerRect, index) => {
            if (headerRect.top <= 0) {
                activeHeaderIndex = index;
            }
        });
        const activeHeader = headers[activeHeaderIndex];
        const nextHeaderRect = headerRects[activeHeaderIndex + 1];
        const pushDistance = nextHeaderRect
            ? Math.max(-floatingHeaderHeight, Math.min(0, nextHeaderRect.top - floatingHeaderHeight))
            : 0;
        eventStickyDayHeader.style.setProperty('--event-feed-day-push', `${pushDistance}px`);

        const nextDate = activeHeader.dataset.dateKey || '';
        if (!nextDate || nextDate === activeStickyDate) {
            return;
        }

        const shouldAnimateDateChange = activeStickyDate !== '';
        activeStickyDate = nextDate;
        eventStickyDayLabel.textContent = activeHeader.dataset.dateLabel || '';
        eventStickyDayHeader.classList.remove('is-changing');
        if (shouldAnimateDateChange) {
            window.requestAnimationFrame(() => eventStickyDayHeader?.classList.add('is-changing'));
        }
    }

    function requestEventStickyDayUpdate() {
        if (stickyDayUpdatePending) {
            return;
        }

        stickyDayUpdatePending = true;
        window.requestAnimationFrame(updateEventStickyDayHeader);
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
                eventDetailOverlayContent.classList.remove('is-swipe-scroll-locked');
                delete eventDetailOverlayContent.dataset.eventSwipeScrollTop;
            }
            eventDetailOverlayContent?.replaceChildren();
            refreshVisibleEvents();
        }, 110);

        if (options.useHistory && history.state?.craftcrawlEventDetailOverlay) {
            history.back();
        }

        return true;
    }

    function dismissEventDetailOverlay(options = {}) {
        if (!eventDetailOverlay || eventDetailOverlay.hidden) {
            return false;
        }

        const shouldRestoreUrl = options.restoreUrl !== false
            && history.state?.craftcrawlEventDetailOverlay;
        closeEventDetailOverlay({ useHistory: false });

        if (shouldRestoreUrl) {
            history.replaceState({ craftcrawlUserShell: true }, '', 'events.php');
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
    window.CraftCrawlDismissEventDetailOverlay = dismissEventDetailOverlay;

    window.addEventListener('popstate', () => {
        if (eventDetailOverlay && !eventDetailOverlay.hidden && !history.state?.craftcrawlEventDetailOverlay) {
            closeEventDetailOverlay({ useHistory: false });
        }
    });

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

    window.addEventListener('scroll', requestEventStickyDayUpdate, { passive: true });
    window.addEventListener('resize', requestEventStickyDayUpdate);
    document.addEventListener('scroll', requestEventStickyDayUpdate, { capture: true, passive: true });

    window.addEventListener('craftcrawl:user-tab-changed', (event) => {
        if (event.detail?.tab !== 'events') {
            hideEventStickyDayHeader();
            return;
        }

        requestEventStickyDayUpdate();
        refreshVisibleEvents();
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            hideEventStickyDayHeader();
            return;
        }

        refreshVisibleEvents();
        requestEventStickyDayUpdate();
    });

    window.addEventListener('pagehide', hideEventStickyDayHeader);
    window.addEventListener('pageshow', requestEventStickyDayUpdate);

    getAllEvents(Boolean(likedEventsOnly?.checked));
    return true;
};

window.CraftCrawlInitEventDetail = function (root = document) {
    const detailPage = root.querySelector('.event-detail-page');

    if (!detailPage || detailPage.dataset.eventDetailReady === 'true') {
        return false;
    }
    detailPage.dataset.eventDetailReady = 'true';

    function ensureEventCoverLightbox() {
        let lightbox = document.getElementById('event-cover-lightbox');

        if (lightbox) {
            return lightbox;
        }

        lightbox = document.createElement('div');
        lightbox.className = 'event-cover-lightbox';
        lightbox.id = 'event-cover-lightbox';
        lightbox.hidden = true;
        lightbox.innerHTML = `
            <div class="event-cover-lightbox-backdrop" data-event-cover-close></div>
            <div class="event-cover-lightbox-stage" data-event-cover-stage>
                <img class="event-cover-lightbox-image" alt="">
            </div>
            <div class="event-cover-lightbox-controls">
                <button type="button" data-event-cover-zoom-out aria-label="Zoom out">-</button>
                <button type="button" data-event-cover-zoom-in aria-label="Zoom in">+</button>
                <button type="button" data-event-cover-reset aria-label="Reset zoom">1x</button>
            </div>
            <button type="button" class="event-cover-lightbox-close" data-event-cover-close aria-label="Close photo viewer">&times;</button>
        `;
        document.body.appendChild(lightbox);
        return lightbox;
    }

    function openEventCoverLightbox(url, alt = '') {
        const lightbox = ensureEventCoverLightbox();
        const stage = lightbox.querySelector('[data-event-cover-stage]');
        const image = lightbox.querySelector('.event-cover-lightbox-image');
        const pointers = new Map();
        let scale = 1;
        let translateX = 0;
        let translateY = 0;
        let dragStart = null;
        let pinchStart = null;

        function applyTransform() {
            image.style.transform = `translate3d(${translateX}px, ${translateY}px, 0) scale(${scale})`;
            lightbox.classList.toggle('is-zoomed', scale > 1.01);
        }

        function clampPan() {
            if (scale <= 1.01) {
                translateX = 0;
                translateY = 0;
                return;
            }

            const bounds = stage.getBoundingClientRect();
            const maxX = bounds.width * (scale - 1) / 2;
            const maxY = bounds.height * (scale - 1) / 2;
            translateX = Math.max(-maxX, Math.min(maxX, translateX));
            translateY = Math.max(-maxY, Math.min(maxY, translateY));
        }

        function setScale(nextScale) {
            scale = Math.max(1, Math.min(4, nextScale));
            clampPan();
            applyTransform();
        }

        function reset() {
            scale = 1;
            translateX = 0;
            translateY = 0;
            applyTransform();
        }

        function close() {
            lightbox.hidden = true;
            image.removeAttribute('src');
            image.style.transform = '';
            document.body.classList.remove('lightbox-open');
            lightbox._craftcrawlEventCoverAbort?.abort();
        }

        lightbox._craftcrawlEventCoverAbort?.abort();
        const abort = new AbortController();
        lightbox._craftcrawlEventCoverAbort = abort;

        image.src = url;
        image.alt = alt;
        reset();
        lightbox.hidden = false;
        document.body.classList.add('lightbox-open');

        lightbox.querySelectorAll('[data-event-cover-close]').forEach((control) => {
            control.addEventListener('click', close, { signal: abort.signal });
        });
        lightbox.querySelector('[data-event-cover-zoom-in]')?.addEventListener('click', () => setScale(scale + 0.5), { signal: abort.signal });
        lightbox.querySelector('[data-event-cover-zoom-out]')?.addEventListener('click', () => setScale(scale - 0.5), { signal: abort.signal });
        lightbox.querySelector('[data-event-cover-reset]')?.addEventListener('click', reset, { signal: abort.signal });
        lightbox.addEventListener('wheel', (event) => {
            event.preventDefault();
            setScale(scale + (event.deltaY < 0 ? 0.2 : -0.2));
        }, { passive: false, signal: abort.signal });
        lightbox.addEventListener('dblclick', (event) => {
            event.preventDefault();
            setScale(scale > 1.01 ? 1 : 2);
        }, { signal: abort.signal });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !lightbox.hidden) {
                close();
            }
        }, { signal: abort.signal });

        stage.addEventListener('pointerdown', (event) => {
            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            stage.setPointerCapture?.(event.pointerId);

            if (pointers.size === 1 && scale > 1.01) {
                dragStart = { x: event.clientX, y: event.clientY, translateX, translateY };
            }

            if (pointers.size === 2) {
                const values = Array.from(pointers.values());
                pinchStart = {
                    distance: Math.hypot(values[1].x - values[0].x, values[1].y - values[0].y),
                    scale
                };
                dragStart = null;
            }
        }, { signal: abort.signal });

        stage.addEventListener('pointermove', (event) => {
            if (!pointers.has(event.pointerId)) {
                return;
            }

            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

            if (pointers.size === 2 && pinchStart) {
                event.preventDefault();
                const values = Array.from(pointers.values());
                const distance = Math.hypot(values[1].x - values[0].x, values[1].y - values[0].y);
                setScale(pinchStart.scale * (distance / Math.max(1, pinchStart.distance)));
                return;
            }

            if (dragStart && scale > 1.01) {
                event.preventDefault();
                translateX = dragStart.translateX + event.clientX - dragStart.x;
                translateY = dragStart.translateY + event.clientY - dragStart.y;
                clampPan();
                applyTransform();
            }
        }, { signal: abort.signal });

        function endPointer(event) {
            pointers.delete(event.pointerId);
            dragStart = null;
            pinchStart = null;
        }

        stage.addEventListener('pointerup', endPointer, { signal: abort.signal });
        stage.addEventListener('pointercancel', endPointer, { signal: abort.signal });
    }

    detailPage.querySelectorAll('[data-event-cover-lightbox]').forEach((button) => {
        button.addEventListener('click', () => {
            openEventCoverLightbox(button.dataset.eventCoverUrl, button.querySelector('img')?.alt || '');
        });
    });

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
                    button.innerHTML = `<span class="pin-icon" aria-hidden="true"></span> Want to Go ${Number(data.count || 0)}`;
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
            event.preventDefault();
            event.stopPropagation();
            if (overlay && typeof window.CraftCrawlDismissEventDetailOverlay === 'function') {
                window.CraftCrawlDismissEventDetailOverlay();
                return;
            }

            window.location.href = link.href;
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
