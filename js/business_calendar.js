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

    // --- Event detail modal ---

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

    // --- Event listeners ---

    // Tab clicks
    tabs.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-calendar-view]');
        if (btn) switchView(btn.dataset.calendarView);
    });

    // Month view: click a day cell to switch to day view
    root.addEventListener('click', function (e) {
        var cell = e.target.closest('[data-calendar-day-date]');
        if (!cell || e.target.closest('[data-event-id]') || e.target.closest('.event-calendar-more-btn')) return;
        var date = cell.dataset.calendarDayDate;
        switchView('day');
        renderDayView(date);
    });

    // "+N more" button: switch to day view for that day
    root.addEventListener('click', function (e) {
        var moreBtn = e.target.closest('.event-calendar-more-btn');
        if (!moreBtn) return;
        e.stopPropagation();
        var date = moreBtn.dataset.calendarDayDate;
        switchView('day');
        renderDayView(date);
    });

    // Event preview/block click: open modal
    root.addEventListener('click', function (e) {
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

    // Modal close handlers
    root.addEventListener('click', function (e) {
        if (e.target.closest('[data-event-modal-close]')) closeEventModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeEventModal();
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
