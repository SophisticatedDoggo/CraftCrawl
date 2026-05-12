function setupPortalEvents() {
    const likedEventsOnly = document.getElementById('liked-events-only');

    if (!document.getElementById('events-feed')) {
        return;
    }

    getAllEvents(Boolean(likedEventsOnly?.checked));

    if (likedEventsOnly) {
        likedEventsOnly.addEventListener('change', () => {
            getAllEvents(likedEventsOnly.checked);
        });
    }
}

function getAllEvents(likedOnly = false) {
    $.ajax({
        url: likedOnly ? "../mapbox/get_events.php?liked=1" : "../mapbox/get_events.php",
        dataType: "json",
        success: function (eventData) {
            renderEventsFeed(eventData);
        },
        error: function (error) {
            console.log(error);
        }
    });
}

function renderEventsFeed(events) {
    const feedContainer = document.getElementById('events-feed');

    if (!feedContainer) {
        return;
    }

    if (!events.length) {
        feedContainer.innerHTML = '<p>No upcoming events yet.</p>';
        return;
    }

    feedContainer.innerHTML = events.map((event) => {
        const eventDate = new Date(`${event.date}T${event.startTime}`);
        const endTime = event.endTime ? ` - ${formatEventTime(event.endTime)}` : '';
        const eventUrl = `../event_details.php?id=${encodeURIComponent(event.id)}&date=${encodeURIComponent(event.date)}`;
        const eventName = escapeHtml(event.name);
        const businessName = escapeHtml(event.businessName);
        const city = escapeHtml(event.city);
        const state = escapeHtml(event.state);
        const description = event.description ? escapeHtml(event.description) : '';
        const coverPhoto = event.coverPhotoUrl
            ? `<img class="event-feed-cover" src="${event.coverPhotoUrl}" alt="">`
            : '';

        return `
            <article class="event-feed-item ${event.coverPhotoUrl ? 'event-feed-item-with-cover' : ''}">
                <div class="event-feed-date">
                    <strong>${eventDate.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}</strong>
                    <span>${eventDate.toLocaleDateString(undefined, { weekday: 'short' })}</span>
                </div>
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

    feedContainer.querySelectorAll('[data-event-want]').forEach((button) => {
        button.addEventListener('click', () => {
            const formData = new FormData();
            formData.append('csrf_token', window.CRAFTCRAWL_CSRF_TOKEN || '');
            formData.append('event_id', button.dataset.eventId);
            formData.append('occurrence_date', button.dataset.occurrenceDate);
            formData.append('is_saved', button.dataset.isSaved || '0');
            button.disabled = true;

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
                    window.dispatchEvent(new CustomEvent('craftcrawl:event-want-updated'));
                })
                .finally(() => {
                    button.disabled = false;
                });
        });
    });
}

function formatBusinessType(type) {
    const labels = {
        brewery: 'Brewery',
        winery: 'Winery',
        cidery: 'Cidery',
        distillery: 'Distillery',
        meadery: 'Meadery'
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

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

setupPortalEvents();
