window.CraftCrawlInitBusinessCalendar = function (root = document) {
    var tabs = root.querySelector('[data-calendar-view-tabs]');
    if (!tabs || tabs.dataset.calendarReady === 'true') return;
    tabs.dataset.calendarReady = 'true';

    var STORAGE_KEY = 'craftcrawl_calendar_view';
    var panels = root.querySelectorAll('[data-calendar-view-panel]');
    var events = window.CraftCrawlCalendarEvents || [];
    var selectedDay = new Date().toISOString().slice(0, 10);

    // --- Helper functions ---

    function escapeHtml(str) {
        var el = document.createElement('span');
        el.textContent = str || '';
        return el.innerHTML;
    }

    function formatTime(timeStr) {
        if (!timeStr) return '';
        var parts = timeStr.split(':');
        var h = parseInt(parts[0]);
        var m = parts[1] || '00';
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return h + ':' + m + ' ' + ampm;
    }

    function formatDate(dateStr) {
        var d = new Date(dateStr + 'T12:00:00');
        return d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    }

    // --- View switching ---

    function switchView(view, skipSave) {
        panels.forEach(function (p) {
            p.hidden = p.dataset.calendarViewPanel !== view;
        });
        tabs.querySelectorAll('[data-calendar-view]').forEach(function (btn) {
            var active = btn.dataset.calendarView === view;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (view === 'day') {
            renderDayView(selectedDay);
        }
        if (!skipSave) {
            try { localStorage.setItem(STORAGE_KEY, view); } catch (e) {}
        }
        window.CraftCrawlUpdateSubtabThumb?.(tabs, !skipSave);
    }

    function scrollToDate(dateStr) {
        var header = root.querySelector('[data-agenda-date="' + dateStr + '"]');
        if (header) {
            header.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // --- Day view rendering ---

    function renderDayView(dateStr) {
        selectedDay = dateStr;
        var timeline = root.querySelector('[data-day-timeline]');
        var label = root.querySelector('[data-day-label]');
        if (!timeline || !label) return;

        var d = new Date(dateStr + 'T00:00:00');
        label.textContent = d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });

        var dayEvents = events.filter(function (e) { return e.date === dateStr; });

        // Build 24-hour timeline (6 AM to 11 PM)
        var html = '';
        for (var hour = 6; hour <= 23; hour++) {
            var hourLabel = hour === 0 ? '12 AM' : hour < 12 ? hour + ' AM' : hour === 12 ? '12 PM' : (hour - 12) + ' PM';
            html += '<div class="calendar-day-hour-row">';
            html += '<span class="calendar-day-hour-label">' + hourLabel + '</span>';
            html += '<div class="calendar-day-hour-cell" data-hour="' + hour + '"></div>';
            html += '</div>';
        }
        timeline.innerHTML = html;

        // Position events on the timeline
        dayEvents.forEach(function (ev) {
            var startParts = ev.startTime.split(':');
            var startHour = parseInt(startParts[0]);

            var cell = timeline.querySelector('[data-hour="' + startHour + '"]');
            if (!cell) return;

            var block = document.createElement('button');
            block.type = 'button';
            block.className = 'calendar-day-event-block';
            block.dataset.eventId = ev.id;
            block.dataset.eventDate = ev.date;

            var timeStr = formatTime(ev.startTime);
            if (ev.endTime) timeStr += ' – ' + formatTime(ev.endTime);

            block.innerHTML = '<span class="day-event-time">' + escapeHtml(timeStr) + '</span>' +
                              '<strong>' + escapeHtml(ev.name) + '</strong>';
            cell.appendChild(block);
        });
    }

    // --- Event detail modal (used by day view and agenda view) ---

    function openEventModal(eventId, dateStr) {
        var ev = events.find(function (e) { return e.id === parseInt(eventId) && e.date === dateStr; });
        if (!ev) return;

        var modal = root.querySelector('[data-event-modal]');
        if (!modal) return;

        modal.querySelector('[data-event-modal-title]').textContent = ev.name;

        var body = modal.querySelector('[data-event-modal-body]');
        var timeStr = formatTime(ev.startTime);
        if (ev.endTime) timeStr += ' – ' + formatTime(ev.endTime);

        body.innerHTML = '<p class="event-modal-date">' + escapeHtml(formatDate(ev.date)) + '</p>' +
                         '<p class="event-modal-time">' + escapeHtml(timeStr) + '</p>' +
                         (ev.description ? '<p class="event-modal-desc">' + escapeHtml(ev.description) + '</p>' : '') +
                         (ev.comments > 0 ? '<a class="event-modal-comments" href="event_comments.php?item=' + encodeURIComponent('event:' + ev.id + ':' + ev.date) + '">' + ev.comments + ' comment' + (ev.comments !== 1 ? 's' : '') + (ev.unread > 0 ? ' <span>' + ev.unread + ' new</span>' : '') + '</a>' : '');

        var actions = modal.querySelector('[data-event-modal-actions]');
        actions.innerHTML = '<a href="event_edit.php?month=' + encodeURIComponent(window.CraftCrawlCalendarMonth || '') + '&edit=' + ev.id + '&date=' + encodeURIComponent(ev.date) + '">Edit Event</a>';

        modal.hidden = false;
        document.body.classList.add('welcome-modal-open');
    }

    function closeEventModal() {
        var modal = root.querySelector('[data-event-modal]');
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('welcome-modal-open');
    }

    // --- Day events modal (used by month view) ---

    function openDayEventsModal(dateStr) {
        var modal = root.querySelector('[data-day-events-modal]');
        if (!modal) return;

        modal.querySelector('[data-day-events-modal-title]').textContent = formatDate(dateStr);

        var listPanel = modal.querySelector('[data-day-events-list]');
        var formPanel = modal.querySelector('[data-day-events-form]');
        formPanel.hidden = true;
        modal.classList.remove('is-form-visible');

        var dayEvents = events.filter(function (e) { return e.date === dateStr; });
        var html = '';

        if (dayEvents.length === 0) {
            html += '<p class="day-events-empty">No events on this day.</p>';
        } else {
            dayEvents.forEach(function (ev) {
                var timeStr = formatTime(ev.startTime);
                if (ev.endTime) timeStr += ' – ' + formatTime(ev.endTime);
                html += '<article class="day-events-item">';
                html += '<div class="day-events-item-time">' + escapeHtml(timeStr) + '</div>';
                html += '<div class="day-events-item-body">';
                html += '<strong>' + escapeHtml(ev.name) + '</strong>';
                if (ev.description) {
                    html += '<p>' + escapeHtml(ev.description.length > 100 ? ev.description.slice(0, 100) + '...' : ev.description) + '</p>';
                }
                html += '<div class="day-events-item-meta">';
                if (ev.comments > 0) {
                    html += '<a class="calendar-agenda-comment-badge' + (ev.unread > 0 ? ' has-unread' : '') + '" href="event_comments.php?item=' + encodeURIComponent('event:' + ev.id + ':' + ev.date) + '">';
                    html += ev.comments + ' comment' + (ev.comments !== 1 ? 's' : '');
                    if (ev.unread > 0) html += ' <span>' + ev.unread + ' new</span>';
                    html += '</a>';
                }
                html += '<a class="calendar-agenda-edit-link" href="event_edit.php?month=' + encodeURIComponent(window.CraftCrawlCalendarMonth || '') + '&edit=' + ev.id + '&date=' + encodeURIComponent(ev.date) + '">Edit</a>';
                html += '</div></div></article>';
            });
        }

        html += '<button type="button" class="day-events-add-btn" data-day-events-add>+ Add Event</button>';
        listPanel.innerHTML = html;

        var dateInput = modal.querySelector('[data-day-events-form-date]');
        if (dateInput) dateInput.value = dateStr;

        modal.hidden = false;
        document.body.classList.add('welcome-modal-open');
    }

    function closeDayEventsModal() {
        var modal = root.querySelector('[data-day-events-modal]');
        if (!modal) return;
        modal.hidden = true;
        modal.classList.remove('is-form-visible');
        document.body.classList.remove('welcome-modal-open');
        var form = modal.querySelector('[data-day-events-create-form]');
        if (form) form.reset();
        var formPanel = modal.querySelector('[data-day-events-form]');
        if (formPanel) formPanel.hidden = true;
        var existingMsg = modal.querySelector('.form-message');
        if (existingMsg) existingMsg.remove();
    }

    // --- Event listeners ---

    // Tab clicks
    tabs.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-calendar-view]');
        if (btn) switchView(btn.dataset.calendarView);
    });

    // Month view: click a day cell to open day events modal
    root.addEventListener('click', function (e) {
        var monthPanel = root.querySelector('[data-calendar-view-panel="month"]');
        if (!monthPanel || monthPanel.hidden) return;

        var cell = e.target.closest('[data-calendar-day-date]');
        if (!cell) return;

        openDayEventsModal(cell.dataset.calendarDayDate);
    });

    // Event preview/block click: open single-event modal (day view and agenda view only)
    root.addEventListener('click', function (e) {
        var monthPanel = root.querySelector('[data-calendar-view-panel="month"]');
        if (monthPanel && !monthPanel.hidden) return;

        var preview = e.target.closest('[data-event-id]');
        if (preview) {
            e.stopPropagation();
            openEventModal(preview.dataset.eventId, preview.dataset.eventDate);
        }
    });

    // Day view navigation (prev/next)
    root.addEventListener('click', function (e) {
        if (e.target.closest('[data-day-prev]')) {
            var prev = new Date(selectedDay + 'T12:00:00');
            prev.setDate(prev.getDate() - 1);
            renderDayView(prev.toISOString().slice(0, 10));
        }
        if (e.target.closest('[data-day-next]')) {
            var next = new Date(selectedDay + 'T12:00:00');
            next.setDate(next.getDate() + 1);
            renderDayView(next.toISOString().slice(0, 10));
        }
    });

    // Single-event modal close
    root.addEventListener('click', function (e) {
        if (e.target.closest('[data-event-modal-close]')) closeEventModal();
    });

    // Day events modal close
    root.addEventListener('click', function (e) {
        if (e.target.closest('[data-day-events-modal-close]')) closeDayEventsModal();
    });

    // Day events modal: "Add Event" button -> show form panel
    root.addEventListener('click', function (e) {
        if (!e.target.closest('[data-day-events-add]')) return;
        var modal = root.querySelector('[data-day-events-modal]');
        if (!modal) return;
        var formPanel = modal.querySelector('[data-day-events-form]');
        if (formPanel) formPanel.hidden = false;
        modal.classList.add('is-form-visible');
    });

    // Day events modal: "Back" button -> hide form panel
    root.addEventListener('click', function (e) {
        if (!e.target.closest('[data-day-events-form-back]')) return;
        var modal = root.querySelector('[data-day-events-modal]');
        if (!modal) return;
        var formPanel = modal.querySelector('[data-day-events-form]');
        if (formPanel) formPanel.hidden = true;
        modal.classList.remove('is-form-visible');
    });

    // Day events modal: form submission via fetch
    root.addEventListener('submit', function (e) {
        var form = e.target.closest('[data-day-events-create-form]');
        if (!form) return;
        e.preventDefault();

        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        var existingMsg = form.querySelector('.form-message');
        if (existingMsg) existingMsg.remove();

        fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.ok) {
                window.location.reload();
            } else {
                if (submitBtn) submitBtn.disabled = false;
                var p = document.createElement('p');
                p.className = 'form-message form-message-error';
                var messages = {
                    event_error: 'Please enter an event name, start time, and a valid date.',
                    recurrence_error: 'Please choose a valid recurrence option.',
                    event_photo_server_limit_error: 'That photo is too large. Try a smaller image.',
                    event_photo_error: 'Cover photo could not be uploaded. Try a JPEG, PNG, or WebP under 12 MB.',
                    csrf_error: 'Your session has expired. Please reload the page and try again.'
                };
                p.textContent = messages[data.message] || 'Something went wrong. Please try again.';
                form.insertBefore(p, form.firstChild);
            }
        })
        .catch(function () {
            if (submitBtn) submitBtn.disabled = false;
            var p = document.createElement('p');
            p.className = 'form-message form-message-error';
            p.textContent = 'Something went wrong. Please try again.';
            form.insertBefore(p, form.firstChild);
        });
    });

    // Recurring checkbox toggle for the modal form
    root.addEventListener('change', function (e) {
        if (e.target.id !== 'modal_is_recurring') return;
        var fields = root.querySelector('#modal_recurrence_fields');
        if (fields) fields.classList.toggle('recurrence-fields-hidden', !e.target.checked);
    });

    // Escape key closes modals
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeEventModal();
            closeDayEventsModal();
        }
    });

    // Today button
    root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-calendar-today]');
        if (!btn) return;
        var today = new Date();
        var todayStr = today.toISOString().slice(0, 10);
        var todayHeader = root.querySelector('[data-agenda-date="' + todayStr + '"]');
        if (todayHeader) {
            switchView('agenda');
            requestAnimationFrame(function () { scrollToDate(todayStr); });
        } else {
            // Today not in current month — navigate via URL
            var url = new URL(window.location.href);
            url.searchParams.set('month', todayStr.slice(0, 7));
            window.location.href = url.toString();
        }
    });

    // --- Initial view ---
    var saved = null;
    try { saved = localStorage.getItem(STORAGE_KEY); } catch (e) {}
    if (!saved) {
        saved = window.innerWidth < 768 ? 'agenda' : 'month';
    }
    if (root.querySelector('[data-calendar-view-panel="' + saved + '"]')) {
        switchView(saved, true);
    }
    requestAnimationFrame(function () {
        window.CraftCrawlUpdateSubtabThumb?.(tabs, false);
        if (saved === 'agenda') {
            var todayStr = new Date().toISOString().slice(0, 10);
            var todayHeader = root.querySelector('[data-agenda-date="' + todayStr + '"]');
            if (todayHeader) {
                todayHeader.scrollIntoView({ block: 'start' });
            } else {
                var firstFuture = root.querySelector('.calendar-agenda-group-header:not(:first-child)');
                if (firstFuture) firstFuture.scrollIntoView({ block: 'start' });
            }
        }
    });
};

window.CraftCrawlInitBusinessCalendar();
