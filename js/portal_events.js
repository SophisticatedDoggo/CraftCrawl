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

        feedContainer.innerHTML = `
            <div class="event-feed-date-rail" aria-hidden="true">
                <div class="event-feed-date-rail-track">
                    <span data-event-date-rail-label></span>
                </div>
            </div>
            ${eventsMarkup}
        `;
        setupEventDateRail(feedContainer);

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

    function setupEventDateRail(container) {
        const rail = container.querySelector('[data-event-date-rail-label]');
        if (!rail) {
            return;
        }

        const state = container.eventDateRailState || {
            activeDate: '',
            ticking: false
        };
        state.rail = rail;
        state.activeDate = '';
        container.eventDateRailState = state;

        function updateRail() {
            state.ticking = false;
            const headers = Array.from(container.querySelectorAll('[data-event-day-header]'));
            if (!headers.length || !state.rail) {
                return;
            }

            const threshold = Math.min(window.innerHeight * 0.28, 160);
            let activeHeader = headers[0];

            headers.forEach((header) => {
                if (header.getBoundingClientRect().top <= threshold) {
                    activeHeader = header;
                }
            });

            const nextDate = activeHeader.dataset.dateKey || '';
            if (nextDate && nextDate !== state.activeDate) {
                state.activeDate = nextDate;
                state.rail.textContent = activeHeader.dataset.dateLabel || '';
                state.rail.classList.remove('is-changing');
                window.requestAnimationFrame(() => state.rail?.classList.add('is-changing'));
            }
        }

        function requestUpdate() {
            if (state.ticking) {
                return;
            }
            state.ticking = true;
            window.requestAnimationFrame(updateRail);
        }

        if (container.dataset.eventDateRailReady !== 'true') {
            container.dataset.eventDateRailReady = 'true';
            window.addEventListener('scroll', requestUpdate, { passive: true });
            window.addEventListener('resize', requestUpdate);
        }

        updateRail();
        window.setTimeout(updateRail, 80);
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
