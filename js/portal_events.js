window.CraftCrawlInitPortalEvents = function (root = document) {
    const feedContainer = root.querySelector('#events-feed');
    const likedEventsOnly = root.querySelector('#liked-events-only');

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
                <article class="event-feed-item ${event.coverPhotoUrl ? 'event-feed-item-with-cover' : ''}">
                    ${coverPhoto}
                    <div class="event-feed-details">
                        <h3>${eventName}</h3>
                        <p>${formatEventTime(event.startTime)}${endTime} &middot; ${businessName}</p>
                        <p>${formatBusinessType(event.businessType)} &middot; ${city}, ${state}</p>
                        ${description ? `<p>${description}</p>` : ''}
                    </div>
                    <div class="event-feed-actions">
                        <a href="${eventUrl}">View event</a>
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
    }

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

window.CraftCrawlInitPortalEvents();
