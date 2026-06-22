(function () {
    'use strict';

    var profilePage = document.querySelector('.profile-page');
    if (!profilePage) return;

    var grid = profilePage.querySelector('[data-profile-photo-grid]');
    var feedView = profilePage.querySelector('[data-profile-feed-view]');
    var feedItems = profilePage.querySelector('[data-profile-feed-items]');
    var feedBackButton = profilePage.querySelector('[data-profile-feed-back]');
    var gridLoadMore = profilePage.querySelector('[data-profile-grid-load-more]');
    var gridLoadMoreBtn = profilePage.querySelector('[data-profile-load-more]');
    var feedLoadMore = profilePage.querySelector('[data-profile-feed-load-more]');
    var feedLoadMoreBtn = profilePage.querySelector('[data-profile-feed-load-more-btn]');
    var csrfToken = profilePage.dataset.csrfToken || '';
    var profileId = grid ? grid.dataset.profileId : '';
    var profileUsername = grid ? grid.dataset.profileUsername : '';

    var gridScrollPosition = 0;
    var feedLoading = false;
    var gridLoading = false;

    var reactionIcons = {
        cheers: '<svg class="feed-reaction-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="1.91"><path d="M17.75,6.27a1.9,1.9,0,0,1-.95,1.65V7.23H12a1,1,0,0,0-.95.95V9.61a1.44,1.44,0,1,1-2.87,0V8.18a.94.94,0,0,0-.95-.95H5.34v.69a1.9,1.9,0,0,1-1-1.65A1.92,1.92,0,0,1,6.3,4.36,1.91,1.91,0,0,1,8.2,2.45a1.93,1.93,0,0,1,1.07.33,1.9,1.9,0,0,1,3.59,0,2,2,0,0,1,1.07-.33,1.92,1.92,0,0,1,1.91,1.91A1.92,1.92,0,0,1,17.75,6.27Z"/><path d="M16.8,7.23V20.59a1.91,1.91,0,0,1-1.91,1.91H7.25a1.91,1.91,0,0,1-1.91-1.91V7.23H7.25a.94.94,0,0,1,.95.95V9.61a1.44,1.44,0,1,0,2.87,0V8.18A1,1,0,0,1,12,7.23Z"/><path d="M16.8,10.09H18.7A1.91,1.91,0,0,1,20.61,12v3.82a1.91,1.91,0,0,1-1.91,1.91H16.8a0,0,0,0,1,0,0V10.09A0,0,0,0,1,16.8,10.09Z"/></svg>',
        nice_find: '<svg class="feed-reaction-icon" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="M10.0284 1.11813C9.69728 1.2952 9.53443 1.61638 9.49957 1.97965C9.48456 2.15538 9.46201 2.32986 9.43136 2.50363C9.3663 2.87248 9.24303 3.3937 9.01205 3.98313C8.5513 5.15891 7.67023 6.58926 5.96985 7.65195C3.57358 9.14956 2.68473 12.5146 3.06456 15.527C3.45234 18.6026 5.20871 21.7903 8.68375 22.9486C9.03 23.0641 9.41163 22.9817 9.67942 22.7337C10.0071 22.4303 10.0238 22.0282 9.94052 21.6223C9.87941 21.3244 9.74999 20.5785 9.74999 19.6875C9.74999 19.3992 9.76332 19.1034 9.79413 18.8068C10.3282 20.031 11.0522 20.9238 11.7758 21.5623C12.8522 22.5121 13.8694 22.8574 14.1722 22.9466C14.402 23.0143 14.6462 23.0185 14.8712 22.9284C17.5283 21.8656 19.2011 20.4232 20.1356 18.7742C21.068 17.1288 21.1993 15.3939 20.9907 13.8648C20.7833 12.3436 20.2354 10.9849 19.7537 10.0215C19.3894 9.29292 19.0534 8.77091 18.8992 8.54242C18.7101 8.26241 18.4637 8.04626 18.1128 8.00636C17.8332 7.97456 17.5531 8.06207 17.3413 8.24739L15.7763 9.61686C15.9107 7.44482 15.1466 5.61996 14.1982 4.24472C13.5095 3.24609 12.7237 2.47913 12.1151 1.96354C11.8094 1.70448 11.5443 1.50549 11.3525 1.36923C11.2564 1.30103 11.1784 1.24831 11.1224 1.21142C10.7908 0.99291 10.3931 0.923125 10.0284 1.11813ZM7.76396 20.256C7.75511 20.0744 7.74999 19.8842 7.74999 19.6875C7.75 18.6347 7.89677 17.3059 8.47802 16.0708C8.67271 15.6572 8.91614 15.254 9.21914 14.8753C9.47408 14.5566 9.89709 14.4248 10.2879 14.5423C10.6787 14.6598 10.959 15.003 10.9959 15.4094C11.2221 17.8977 12.2225 19.2892 13.099 20.0626C13.5469 20.4579 13.979 20.7056 14.292 20.8525C15.5 20.9999 17.8849 18.6892 18.3955 17.7882C19.0569 16.6211 19.1756 15.356 19.0091 14.1351C18.8146 12.7092 18.2304 11.3897 17.7656 10.5337L14.6585 13.2525C14.3033 13.5634 13.779 13.5835 13.401 13.3008C13.023 13.018 12.8942 12.5095 13.092 12.0809C14.4081 9.22933 13.655 6.97987 12.5518 5.38019C12.1138 4.74521 11.6209 4.21649 11.18 3.80695C11.0999 4.088 10.9997 4.39262 10.8742 4.71284C10.696 5.16755 10.4662 5.65531 10.1704 6.15187C9.50801 7.26379 8.51483 8.41987 7.02982 9.34797C5.57752 10.2556 4.71646 12.6406 5.04885 15.2768C5.29944 17.2643 6.20241 19.1244 7.76396 20.256Z"/></svg>',
        heart: '<span class="feed-reaction-icon feed-reaction-icon-heart" aria-hidden="true"></span>',
        yuck: '<span class="feed-reaction-icon feed-reaction-icon-yuck" aria-hidden="true"></span>'
    };

    var reactionTextLabels = {
        cheers: 'Say cheers to this post',
        nice_find: 'Say this post is fire',
        heart: 'Like this post',
        yuck: 'Say yuck to this post'
    };

    var checkinReactionTypes = ['heart', 'cheers', 'nice_find', 'yuck'];

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var h = d.getHours();
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        var min = d.getMinutes().toString().padStart(2, '0');
        return months[d.getMonth()] + ' ' + d.getDate() + ', ' + h + ':' + min + ' ' + ampm;
    }

    // --- Subtab switching ---

    function switchProfileSubtab(target) {
        if (feedView && feedView.classList.contains('is-active')) {
            hideFeedView();
        }

        profilePage.querySelectorAll('[data-profile-subtab]').forEach(function (tab) {
            var isTarget = tab.dataset.profileSubtab === target;
            tab.classList.toggle('is-active', isTarget);
            tab.setAttribute('aria-selected', isTarget ? 'true' : 'false');
        });

        profilePage.querySelectorAll('[data-profile-subtab-panel]').forEach(function (panel) {
            panel.hidden = panel.dataset.profileSubtabPanel !== target;
        });

        var url = new URL(window.location.href);
        if (target === 'posts') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', target);
        }
        history.replaceState(null, '', url.toString());
    }

    profilePage.addEventListener('click', function (e) {
        var subtab = e.target.closest('[data-profile-subtab]');
        if (subtab) {
            switchProfileSubtab(subtab.dataset.profileSubtab);
        }
    });

    var urlParams = new URLSearchParams(window.location.search);
    var initialTab = urlParams.get('tab');
    if (initialTab && profilePage.querySelector('[data-profile-subtab="' + initialTab + '"]')) {
        switchProfileSubtab(initialTab);
    }

    // --- Grid "Load More" ---

    if (gridLoadMoreBtn) {
        gridLoadMoreBtn.addEventListener('click', function () {
            if (gridLoading) return;
            gridLoading = true;
            gridLoadMoreBtn.disabled = true;
            gridLoadMoreBtn.textContent = 'Loading...';

            var lastCell = grid.querySelector('.profile-grid-cell:last-of-type');
            var beforeId = lastCell ? lastCell.dataset.visitId : '';

            fetch('profile_checkins.php?mode=grid&user_id=' + encodeURIComponent(profileId) + '&before_id=' + encodeURIComponent(beforeId), {
                credentials: 'same-origin'
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.ok) return;

                    data.checkins.forEach(function (checkin) {
                        var cell = document.createElement('button');
                        cell.type = 'button';
                        cell.className = 'profile-grid-cell';
                        cell.dataset.feedItemKey = checkin.item_key;
                        cell.dataset.visitId = String(checkin.visit_id);
                        cell.dataset.businessName = checkin.business_name;
                        cell.dataset.businessType = checkin.business_type;

                        if (checkin.has_photo && checkin.photo_url) {
                            var img = document.createElement('img');
                            img.src = checkin.photo_url;
                            img.alt = 'Check-in at ' + checkin.business_name;
                            img.loading = 'lazy';
                            cell.appendChild(img);
                        } else {
                            var placeholder = document.createElement('div');
                            placeholder.className = 'profile-grid-placeholder';
                            placeholder.innerHTML = '<span>' + escapeHtml(checkin.business_name) + '</span><small>' + escapeHtml(checkin.business_type_label) + '</small>';
                            cell.appendChild(placeholder);
                        }

                        grid.appendChild(cell);
                    });

                    if (!data.has_more && gridLoadMore) {
                        gridLoadMore.hidden = true;
                    }
                })
                .finally(function () {
                    gridLoading = false;
                    gridLoadMoreBtn.disabled = false;
                    gridLoadMoreBtn.textContent = 'Load More';
                });
        });
    }

    // --- Grid → Feed transition ---

    if (!grid || !feedView || !feedItems) return;

    function renderFeedCard(item) {
        var date = formatDate(item.created_at);
        var actorName = item.is_self ? 'You' : (item.friend_name || 'A friend');
        var actor = item.actor || {};
        var frame = String(actor.frame || '').replace(/[^a-z0-9_-]/gi, '');
        var frameStyle = String(actor.frame_style || 'solid').replace(/[^a-z0-9_-]/gi, '') || 'solid';
        var avatarClasses = 'user-avatar user-avatar-medium feed-avatar' + (frame ? ' has-frame-' + frame + ' has-frame-style-' + frameStyle : '');
        var initials = actor.initials || actorName.split(/\s+/).slice(0, 2).map(function (p) { return p.charAt(0); }).join('').toUpperCase() || 'CC';

        var avatarHtml;
        if (actor.avatar_url) {
            avatarHtml = '<span class="' + avatarClasses + '"><img src="' + escapeHtml(actor.avatar_url) + '" alt="' + escapeHtml(actorName) + ' profile photo"></span>';
        } else {
            avatarHtml = '<span class="' + avatarClasses + '" aria-label="' + escapeHtml(actorName) + ' profile photo"><span>' + escapeHtml(initials) + '</span></span>';
        }

        var profileUrl = actor.id ? 'profile.php?id=' + encodeURIComponent(actor.id) : '';
        if (profileUrl) {
            avatarHtml = '<a class="user-avatar-link feed-avatar-link" href="' + profileUrl + '">' + avatarHtml + '</a>';
        }

        var photoHtml = '';
        if (item.photo_url) {
            photoHtml = '<div class="feed-checkin-photo"><img src="' + escapeHtml(item.photo_url) + '" alt="Check-in photo at ' + escapeHtml(item.business_name) + '"></div>';
        }

        var visitLabel = item.visit_type === 'first_time' ? ' for the first time' : '';
        var businessLink = '<a class="feed-business-link" href="../business_details.php?id=' + encodeURIComponent(item.business_id) + '">' + escapeHtml(item.business_name) + '</a>';

        var captionHtml = '';
        if (item.caption) {
            captionHtml = '<div class="feed-caption-area"><div class="feed-caption-content"><div class="feed-caption-preview-row"><span class="feed-caption-preview">' + escapeHtml(item.caption) + '</span></div></div></div>';
        }

        var reactions = item.reactions || [];
        var reactionMap = {};
        reactions.forEach(function (r) { reactionMap[r.type] = r; });

        var commentCount = Number(item.comment_count || 0);
        var commentLabel = commentCount > 0 ? String(commentCount) : '';
        var commentsHtml = item.item_key
            ? '<button type="button" class="feed-comments-link" data-comments-sheet-trigger data-item-key="' + escapeHtml(item.item_key) + '" aria-label="Show comments"><span class="feed-comments-icon" aria-hidden="true"></span>' + (commentLabel ? '<span class="feed-comment-count">' + commentLabel + '</span>' : '') + '</button>'
            : '';

        var reactionsHtml = '';
        if (item.item_key && (item.allow_interactions !== false || item.is_self)) {
            var reactionTypes = checkinReactionTypes;
            if (item.is_self) {
                reactionTypes = reactionTypes.filter(function (t) { return t !== 'want_to_go'; });
            }
            reactionsHtml = reactionTypes.map(function (type) {
                var reaction = reactionMap[type] || { count: 0, reacted: false };
                return '<button type="button" class="' + (reaction.reacted ? 'is-active' : '') + '" data-feed-reaction data-item-key="' + escapeHtml(item.item_key) + '" data-reaction-type="' + type + '" data-reaction-count="' + Number(reaction.count || 0) + '" aria-label="' + escapeHtml(reactionTextLabels[type] || type) + '" aria-pressed="' + (reaction.reacted ? 'true' : 'false') + '">' + (reactionIcons[type] || '') + '<span class="feed-reaction-count"' + (reaction.count > 0 ? '' : ' hidden') + '>' + (reaction.count > 0 ? reaction.count : '') + '</span></button>';
            }).join('');
        }

        var actionsHtml = '';
        if (item.item_key) {
            actionsHtml = '<div class="feed-action-row feed-action-row-flat"><div class="feed-primary-actions">' + commentsHtml + '</div><div class="feed-reactions">' + reactionsHtml + '</div></div>';
        }

        return '<article class="friends-feed-item feed-checkin-item" data-feed-item-key="' + escapeHtml(item.item_key || '') + '" data-feed-item-type="' + escapeHtml(item.type || '') + '" data-feed-is-self="' + (item.is_self ? 'true' : 'false') + '">' +
            avatarHtml +
            '<div class="feed-item-content">' +
                '<p class="feed-item-meta"><span>' + escapeHtml(actorName) + '</span>' + (date ? '<span aria-hidden="true">&middot;</span><time>' + escapeHtml(date) + '</time>' : '') + '</p>' +
                '<strong class="feed-item-title">Checked in at ' + businessLink + visitLabel + '</strong>' +
                '<p class="feed-item-detail">' + escapeHtml(item.city) + ', ' + escapeHtml(item.state) + '</p>' +
            '</div>' +
            photoHtml +
            '<div class="feed-checkin-below">' + actionsHtml + captionHtml + '</div>' +
        '</article>';
    }

    function showFeedView(targetVisitId) {
        gridScrollPosition = window.scrollY;

        if (grid) grid.style.display = 'none';
        if (gridLoadMore) gridLoadMore.style.display = 'none';
        feedView.classList.add('is-active');

        feedItems.innerHTML = '<p style="text-align: center; padding: 24px 0; color: var(--color-muted);">Loading...</p>';

        var url = 'profile_checkins.php?mode=feed&user_id=' + encodeURIComponent(profileId);
        feedLoading = true;

        fetch(url, { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.ok) {
                    feedItems.innerHTML = '<p style="text-align: center; padding: 24px 0; color: var(--color-muted);">Could not load posts.</p>';
                    return;
                }

                var feed = data.feed || [];
                if (feed.length === 0) {
                    feedItems.innerHTML = '<p style="text-align: center; padding: 24px 0; color: var(--color-muted);">No posts yet.</p>';
                    return;
                }

                feedItems.innerHTML = feed.map(renderFeedCard).join('');

                if (data.has_more && feedLoadMore) {
                    feedLoadMore.hidden = false;
                    var lastItem = feed[feed.length - 1];
                    feedLoadMore.dataset.beforeId = lastItem.visit_id;
                }

                if (window.CraftCrawlInitFeedThread) {
                    window.CraftCrawlInitFeedThread(feedView);
                }

                if (targetVisitId) {
                    var targetCard = feedItems.querySelector('[data-feed-item-key$=":' + CSS.escape(targetVisitId) + '"]');
                    if (targetCard) {
                        requestAnimationFrame(function () {
                            targetCard.scrollIntoView({ behavior: 'instant', block: 'start' });
                            window.scrollBy(0, -60);
                        });
                    }
                } else {
                    window.scrollTo(0, 0);
                }
            })
            .catch(function () {
                feedItems.innerHTML = '<p style="text-align: center; padding: 24px 0; color: var(--color-muted);">Could not load posts.</p>';
            })
            .finally(function () {
                feedLoading = false;
            });

    }

    function hideFeedView() {
        feedView.classList.remove('is-active');
        if (grid) grid.style.display = '';
        if (gridLoadMore) gridLoadMore.style.display = '';
        if (feedLoadMore) feedLoadMore.hidden = true;

        window.scrollTo(0, gridScrollPosition);
    }

    grid.addEventListener('click', function (e) {
        var cell = e.target.closest('.profile-grid-cell');
        if (!cell) return;

        var visitId = cell.dataset.visitId;
        showFeedView(visitId);
    });

    if (feedBackButton) {
        feedBackButton.addEventListener('click', function () {
            hideFeedView();
        });
    }

    // --- Swipe right to dismiss feed view ---

    var swipeStartX = 0;
    var swipeStartY = 0;
    var swipeTracking = false;
    var swipeLocked = false;

    document.addEventListener('touchstart', function (e) {
        if (!feedView.classList.contains('is-active')) return;
        var touch = e.touches[0];
        swipeStartX = touch.clientX;
        swipeStartY = touch.clientY;
        swipeTracking = true;
        swipeLocked = false;
    }, { passive: true });

    document.addEventListener('touchmove', function (e) {
        if (!swipeTracking || !feedView.classList.contains('is-active')) return;
        var touch = e.touches[0];
        var dx = touch.clientX - swipeStartX;
        var dy = Math.abs(touch.clientY - swipeStartY);

        if (!swipeLocked && (Math.abs(dx) > 10 || dy > 10)) {
            swipeLocked = true;
            if (dx <= 0 || dy > Math.abs(dx)) {
                swipeTracking = false;
                return;
            }
        }

        if (swipeLocked && dx > 0) {
            feedView.style.transform = 'translateX(' + dx + 'px)';
            feedView.style.opacity = String(Math.max(0.3, 1 - dx / 300));
        }
    }, { passive: true });

    document.addEventListener('touchend', function (e) {
        if (!swipeTracking || !feedView.classList.contains('is-active')) {
            swipeTracking = false;
            return;
        }
        swipeTracking = false;
        var touch = e.changedTouches[0];
        var dx = touch.clientX - swipeStartX;

        feedView.style.transform = '';
        feedView.style.opacity = '';

        if (dx > 100) {
            hideFeedView();
        }
    }, { passive: true });

    // --- Feed "Load More" ---

    if (feedLoadMoreBtn) {
        feedLoadMoreBtn.addEventListener('click', function () {
            if (feedLoading) return;
            feedLoading = true;
            feedLoadMoreBtn.disabled = true;
            feedLoadMoreBtn.textContent = 'Loading...';

            var beforeId = feedLoadMore.dataset.beforeId || '';

            fetch('profile_checkins.php?mode=feed&user_id=' + encodeURIComponent(profileId) + '&before_id=' + encodeURIComponent(beforeId), {
                credentials: 'same-origin'
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.ok) return;

                    var feed = data.feed || [];
                    var html = feed.map(renderFeedCard).join('');
                    feedItems.insertAdjacentHTML('beforeend', html);

                    if (data.has_more && feed.length > 0) {
                        var lastItem = feed[feed.length - 1];
                        feedLoadMore.dataset.beforeId = lastItem.visit_id;
                    } else {
                        feedLoadMore.hidden = true;
                    }
                })
                .finally(function () {
                    feedLoading = false;
                    feedLoadMoreBtn.disabled = false;
                    feedLoadMoreBtn.textContent = 'Load More';
                });
        });
    }
})();
