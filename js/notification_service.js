(function () {
    if (window.CraftCrawlNotificationService) {
        return;
    }

    var isUserPath = /\/user\/?$|\/user\//.test(window.location.pathname);
    var statusPollInterval = null;
    var statusVersion = 0;
    var currentStatus = {
        pendingInvites: 0,
        pendingRecommendations: 0,
        socialNotifications: 0,
        newFriends: 0,
        newFeedItems: 0,
        pendingChainInvites: 0,
        friendsBadgeCount: 0,
        feedBadgeCount: 0,
        badgeCount: 0
    };

    function userEndpoint(file) {
        return isUserPath ? file : 'user/' + file;
    }

    function setBadge(element, value) {
        if (!element) return;
        element.textContent = value > 9 ? '9+' : String(value);
        element.hidden = value < 1;
    }

    function getNativeBadgePlugin() {
        var capacitor = window.Capacitor;
        if (!capacitor
            || typeof capacitor.isNativePlatform !== 'function'
            || !capacitor.isNativePlatform()
            || (typeof capacitor.getPlatform === 'function' && capacitor.getPlatform() !== 'ios')) {
            return null;
        }

        return capacitor.Plugins?.CraftCrawlBadge
            || (typeof capacitor.registerPlugin === 'function'
                ? capacitor.registerPlugin('CraftCrawlBadge')
                : null);
    }

    function syncNativeAppBadge(value) {
        var badgePlugin = getNativeBadgePlugin();
        if (!badgePlugin || typeof badgePlugin.setBadgeCount !== 'function') {
            return;
        }

        badgePlugin.setBadgeCount({ count: Math.max(0, Number(value || 0)) }).catch(function () {});
    }

    function applyStatusCounts(counts) {
        var pendingInvites = Number(counts.pending_invites || 0);
        var pendingRecommendations = Number(counts.pending_recommendations || 0);
        var socialNotifications = Number(counts.social_notifications || 0);
        var newFriends = Number(counts.new_friends || 0);
        var newFeedItems = Number(counts.new_feed_items || 0);
        var pendingChainInvites = Number(counts.pending_chain_invites || 0);
        var friendsBadgeCount = pendingInvites + pendingRecommendations + newFriends;
        var feedBadgeCount = newFeedItems + socialNotifications;
        var badgeCount = Number(counts.badge_count || 0);

        currentStatus = {
            pendingInvites: pendingInvites,
            pendingRecommendations: pendingRecommendations,
            socialNotifications: socialNotifications,
            newFriends: newFriends,
            newFeedItems: newFeedItems,
            pendingChainInvites: pendingChainInvites,
            friendsBadgeCount: friendsBadgeCount,
            feedBadgeCount: feedBadgeCount,
            badgeCount: badgeCount
        };

        document.querySelectorAll('[data-friends-menu-badge]').forEach(function (b) { setBadge(b, friendsBadgeCount); });
        document.querySelectorAll('[data-friends-menu-toggle-badge]').forEach(function (b) { setBadge(b, friendsBadgeCount); });
        document.querySelectorAll('[data-friends-tab-badge]').forEach(function (b) { setBadge(b, feedBadgeCount); });

        if (pendingChainInvites > 0 && !window._chainInvitesSuppressed) {
            document.querySelectorAll('[data-quests-tab-badge]').forEach(function (b) { setBadge(b, pendingChainInvites); });
        } else if (pendingChainInvites === 0) {
            window._chainInvitesSuppressed = false;
            document.querySelectorAll('[data-quests-tab-badge]').forEach(function (b) { setBadge(b, 0); });
        }

        syncNativeAppBadge(badgeCount);

        window.dispatchEvent(new CustomEvent('craftcrawl:notification-counts-changed', {
            detail: currentStatus
        }));
    }

    function loadStatus() {
        var requestVersion = ++statusVersion;
        return fetch(userEndpoint('friend_status.php'), { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.ok) return;
                if (requestVersion !== statusVersion) return;
                applyStatusCounts(data);
            })
            .catch(function () {});
    }

    function applyServerCounts(data) {
        applyStatusCounts(data?.counts || data || {});
    }

    function startStatusPolling() {
        if (statusPollInterval) return;
        statusPollInterval = setInterval(function () {
            if (!document.hidden) {
                loadStatus();
            }
        }, 45000);
    }

    function stopStatusPolling() {
        if (statusPollInterval) {
            clearInterval(statusPollInterval);
            statusPollInterval = null;
        }
    }

    function getStatus() {
        return Object.assign({}, currentStatus);
    }

    function bumpVersion() {
        return ++statusVersion;
    }

    function getVersion() {
        return statusVersion;
    }

    window.addEventListener('craftcrawl:push-received', function () {
        loadStatus();
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopStatusPolling();
        } else {
            loadStatus();
            startStatusPolling();
        }
    });

    loadStatus();
    startStatusPolling();

    window.CraftCrawlNotificationService = {
        loadStatus: loadStatus,
        getStatus: getStatus,
        applyServerCounts: applyServerCounts,
        bumpVersion: bumpVersion,
        getVersion: getVersion
    };
})();
