window.CraftCrawlInitBusinessCalendar = function (root = document) {
    var tabs = root.querySelector('[data-calendar-view-tabs]');
    if (!tabs || tabs.dataset.calendarReady === 'true') return;
    tabs.dataset.calendarReady = 'true';

    var STORAGE_KEY = 'craftcrawl_calendar_view';
    var panels = root.querySelectorAll('[data-calendar-view-panel]');

    function switchView(view, skipSave) {
        panels.forEach(function (p) {
            p.hidden = p.dataset.calendarViewPanel !== view;
        });
        tabs.querySelectorAll('[data-calendar-view]').forEach(function (btn) {
            var active = btn.dataset.calendarView === view;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (!skipSave) {
            try { localStorage.setItem(STORAGE_KEY, view); } catch (e) {}
        }
    }

    function scrollToDate(dateStr) {
        var header = root.querySelector('[data-agenda-date="' + dateStr + '"]');
        if (header) {
            header.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // Tab clicks
    tabs.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-calendar-view]');
        if (btn) switchView(btn.dataset.calendarView);
    });

    // Month view: click a day cell to jump to that date in agenda
    root.addEventListener('click', function (e) {
        var cell = e.target.closest('[data-calendar-day-date]');
        if (!cell) return;
        var date = cell.dataset.calendarDayDate;
        switchView('agenda');
        requestAnimationFrame(function () { scrollToDate(date); });
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

    // Initial view
    var saved = null;
    try { saved = localStorage.getItem(STORAGE_KEY); } catch (e) {}
    if (!saved) {
        saved = window.innerWidth < 768 ? 'agenda' : 'month';
    }
    if (root.querySelector('[data-calendar-view-panel="' + saved + '"]')) {
        switchView(saved, true);
    }
};

window.CraftCrawlInitBusinessCalendar();
