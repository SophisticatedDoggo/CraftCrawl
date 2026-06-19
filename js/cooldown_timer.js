window.CraftCrawlCooldownTimer = (function () {
    var activeTimers = [];

    function formatRemaining(ms) {
        if (ms <= 0) {
            return null;
        }

        var totalSeconds = Math.ceil(ms / 1000);
        var hours = Math.floor(totalSeconds / 3600);
        var minutes = Math.floor((totalSeconds % 3600) / 60);
        var seconds = totalSeconds % 60;

        if (hours > 0) {
            return hours + 'h ' + minutes + 'm';
        }

        if (minutes > 0) {
            return minutes + 'm ' + seconds + 's';
        }

        return seconds + 's';
    }

    function start(element, isoTimestamp, onExpire) {
        if (!element || !isoTimestamp) {
            return null;
        }

        var target = new Date(isoTimestamp).getTime();

        function tick() {
            var remaining = target - Date.now();

            if (remaining <= 0) {
                element.textContent = 'Available now';
                if (onExpire) {
                    onExpire();
                }
                return;
            }

            element.textContent = formatRemaining(remaining) + ' remaining';
            var timerId = requestAnimationFrame(function () {
                setTimeout(tick, 1000);
            });
            entry.rafId = timerId;
        }

        var entry = { element: element, rafId: null };
        activeTimers.push(entry);
        tick();

        return entry;
    }

    function stop(entry) {
        if (entry && entry.rafId) {
            cancelAnimationFrame(entry.rafId);
            entry.rafId = null;
        }
    }

    return { start: start, stop: stop, formatRemaining: formatRemaining };
})();
