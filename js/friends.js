window.CraftCrawlInitFriends = function (scope = document) {
    const panel = scope.querySelector('[data-friends-panel]');
    const managerPage = scope.querySelector('[data-friends-manager-page]');
    const root = managerPage || scope;
    const form = root.querySelector('[data-friends-search-form]');
    const input = root.querySelector('#friend-search-input');
    const results = root.querySelector('[data-friends-search-results]');
    const requestsList = root.querySelector('[data-friend-requests-list]');
    const currentFriendsList = root.querySelector('[data-current-friends-list]');
    const currentFriendsFilter = root.querySelector('[data-current-friends-filter]');
    const recommendationButtons = root.querySelectorAll('[data-recommendation-id]');
    const feed = panel?.querySelector('[data-friends-feed]');
    const sentinel = feed?.querySelector('[data-feed-sentinel]');
    const status = root.querySelector('[data-friends-status]');
    const count = panel?.querySelector('[data-friends-count]');
    const menuBadge = document.querySelector('[data-friends-menu-badge]');
    const tabBadge = document.querySelector('[data-friends-tab-badge]');
    const csrfToken = panel?.dataset.csrfToken || managerPage?.dataset.csrfToken || '';
    let currentStatus = {
        pendingInvites: 0,
        newFriends: 0,
        newFeedItems: 0,
        badgeCount: 0
    };
    let currentFriendsCache = [];
    let friendSearchTimer = null;
    let friendSearchRequestId = 0;
    let lastItemDate = null;
    let hasMore = false;
    let loadingMore = false;
    const reactionLabels = {
        cheers: '🍻 Cheers',
        nice_find: '🔥 Nice',
        want_to_go: '📍 Want to Go',
        trophy: '🏆 Trophy'
    };
    const reactionTypesByItemType = {
        first_visit: ['cheers', 'nice_find'],
        level_up: ['cheers', 'nice_find', 'trophy'],
        event_want: ['cheers', 'nice_find'],
        location_want: ['cheers', 'nice_find', 'want_to_go'],
        badge_earned: ['cheers', 'trophy'],
        business_post: ['cheers', 'want_to_go']
    };
    const isUserPath = /\/user\/?$|\/user\//.test(window.location.pathname);

    function userEndpoint(file) {
        return isUserPath ? file : `user/${file}`;
    }

    if (!panel && !managerPage && !menuBadge && !tabBadge) {
        return;
    }

    const readyTarget = managerPage || panel;
    if (readyTarget && readyTarget.dataset.friendsReady === 'true') {
        return;
    }
    if (readyTarget) {
        readyTarget.dataset.friendsReady = 'true';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function showStatus(message, isError) {
        if (!status) {
            return;
        }

        status.textContent = message;
        status.classList.toggle('form-message-error', isError);
        status.classList.toggle('form-message-success', !isError);
        status.hidden = false;
    }

    function hideStatus() {
        if (status) {
            status.hidden = true;
        }
    }

    function postForm(url, values) {
        const formData = new FormData();

        Object.keys(values).forEach((key) => {
            formData.append(key, values[key]);
        });

        return fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then((response) => response.json().catch(() => null).then((data) => {
            if (!response.ok || !data) {
                throw new Error((data && data.message) || 'Reaction could not be saved.');
            }

            return data;
        }));
    }

    function formatDate(value) {
        const date = new Date(value.replace(' ', 'T'));

        if (Number.isNaN(date.getTime())) {
            return '';
        }

        return date.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    function renderAvatar(actor, fallbackName) {
        const data = actor || {};
        const frame = String(data.frame || '').replace(/[^a-z0-9_-]/gi, '');
        const frameStyle = String(data.frame_style || 'solid').replace(/[^a-z0-9_-]/gi, '') || 'solid';
        const classes = `user-avatar user-avatar-medium feed-avatar ${frame ? `has-frame-${frame} has-frame-style-${frameStyle}` : ''}`;
        const name = data.name || fallbackName || 'A friend';
        const initials = data.initials || String(name).split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase() || 'CC';

        if (data.avatar_url) {
            return `<span class="${classes}"><img src="${escapeHtml(data.avatar_url)}" alt="${escapeHtml(name)} profile photo" loading="lazy"></span>`;
        }

        return `<span class="${classes}" aria-label="${escapeHtml(name)} profile photo"><span>${escapeHtml(initials)}</span></span>`;
    }

    function feedItemAttrs(item) {
        return `data-feed-item-type="${escapeHtml(item.type || '')}" data-feed-is-self="${item.is_self ? 'true' : 'false'}"`;
    }

    function renderFeedMeta(label, date) {
        return `
            <p class="feed-item-meta">
                <span>${escapeHtml(label)}</span>
                ${date ? `<span aria-hidden="true">·</span><time>${escapeHtml(date)}</time>` : ''}
            </p>
        `;
    }

    function availableReactionTypes(item) {
        const types = reactionTypesByItemType[item.type] || Object.keys(reactionLabels);

        if (item.is_self && item.type !== 'business_post') {
            return types.filter((type) => type !== 'want_to_go');
        }

        return types;
    }

    function loadStatus() {
        return fetch(userEndpoint('friend_status.php'), { credentials: 'same-origin' })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) {
                    return;
                }

                const badgeCount = Number(data.badge_count || 0);
                currentStatus = {
                    pendingInvites: Number(data.pending_invites || 0),
                    newFriends: Number(data.new_friends || 0),
                    newFeedItems: Number(data.new_feed_items || 0),
                    badgeCount
                };
                setBadge(menuBadge, badgeCount);
                setBadge(tabBadge, badgeCount);
            })
            .catch(() => {});
    }

    function markFriendsSeen() {
        if (!csrfToken) {
            return Promise.resolve();
        }

        return postForm(userEndpoint('friend_seen.php'), {
            csrf_token: csrfToken
        })
            .then(() => loadStatus())
            .catch(() => {});
    }

    function setBadge(element, value) {
        if (!element) {
            return;
        }

        element.textContent = value > 9 ? '9+' : String(value);
        element.hidden = value < 1;
    }

    function renderSearchResults(users) {
        if (!results) {
            return;
        }

        if (!users.length) {
            results.innerHTML = '<p>No matching user accounts found.</p>';
            results.hidden = false;
            return;
        }

        results.innerHTML = users.map((user) => {
            let buttonText = 'Invite';
            let disabled = '';
            let action = 'invite';
            let statusMarkup = '';
            let rowClass = '';

            if (user.is_friend) {
                buttonText = 'Added';
                disabled = 'disabled';
                statusMarkup = '<span class="friend-search-status is-friend">Already friends</span>';
            } else if (user.pending_sent) {
                buttonText = 'Cancel Invite';
                action = 'cancel';
                rowClass = ' is-invite-sent';
                statusMarkup = '<span class="friend-search-status is-sent">✓ Invitation sent</span>';
            } else if (user.pending_received) {
                buttonText = 'Accept Invite';
                action = 'accept';
                statusMarkup = '<span class="friend-search-status is-received">Invited you</span>';
            }

            return `
                <article class="friend-search-result${rowClass}">
                    ${renderAvatar(user.actor, user.name)}
                    <div class="friend-search-summary">
                        <strong>${escapeHtml(user.name)}</strong>
                        <span class="friend-search-meta">Level ${escapeHtml(user.level || 1)}${user.title ? ` &middot; ${escapeHtml(user.title)}` : ''}</span>
                        <span class="friend-search-email">${escapeHtml(user.email)}</span>
                        ${statusMarkup}
                    </div>
                    <button type="button" data-friend-id="${user.id}" data-request-id="${user.received_request_id || user.sent_request_id || ''}" data-friend-action="${action}" ${disabled}>
                        ${buttonText}
                    </button>
                </article>
            `;
        }).join('');
        results.hidden = false;

        results.querySelectorAll('button[data-friend-action]').forEach((button) => {
            button.addEventListener('click', () => {
                const action = button.dataset.friendAction;
                button.disabled = true;
                button.classList.add('is-loading');
                button.textContent = action === 'accept'
                    ? 'Accepting...'
                    : action === 'cancel'
                        ? 'Canceling...'
                        : 'Sending...';

                const request = action === 'accept'
                    ? postForm(userEndpoint('friend_respond.php'), {
                        csrf_token: csrfToken,
                        request_id: button.dataset.requestId,
                        response: 'accepted'
                    })
                    : action === 'cancel'
                        ? postForm(userEndpoint('friend_cancel.php'), {
                            csrf_token: csrfToken,
                            request_id: button.dataset.requestId
                        })
                    : postForm(userEndpoint('friend_add.php'), {
                        csrf_token: csrfToken,
                        friend_id: button.dataset.friendId
                    });

                request
                    .then((data) => {
                        if (!data.ok) {
                            showStatus(data.message || 'Friend could not be added.', true);
                            button.disabled = false;
                            button.classList.remove('is-loading');
                            button.textContent = action === 'accept'
                                ? 'Accept Invite'
                                : action === 'cancel'
                                    ? 'Cancel Invite'
                                    : 'Invite';
                            return;
                        }

                        showStatus(data.message || 'Friend invite updated.', false);
                        if (data.xp_reward && window.craftcrawlShowXpReward) {
                            window.craftcrawlShowXpReward(data.xp_reward);
                        }
                        button.classList.remove('is-loading');
                        const row = button.closest('.friend-search-result');
                        const summary = row?.querySelector('.friend-search-summary');

                        if (action === 'cancel') {
                            button.disabled = false;
                            button.textContent = 'Invite';
                            button.dataset.friendAction = 'invite';
                            button.dataset.requestId = '';
                            if (row && summary) {
                                row.classList.remove('is-invite-sent');
                                summary.querySelector('.friend-search-status')?.remove();
                            }
                        } else {
                            button.textContent = data.status === 'pending' ? 'Cancel Invite' : 'Added';
                            if (data.status === 'pending') {
                                button.disabled = false;
                                button.dataset.friendAction = 'cancel';
                                button.dataset.requestId = data.request_id || '';
                            }
                        }

                        if (row && summary && action !== 'cancel') {
                            row.classList.toggle('is-invite-sent', data.status === 'pending');
                            summary.querySelector('.friend-search-status')?.remove();
                            const confirmation = document.createElement('span');
                            confirmation.className = data.status === 'pending'
                                ? 'friend-search-status is-sent'
                                : 'friend-search-status is-friend';
                            confirmation.textContent = data.status === 'pending'
                                ? '✓ Invitation sent'
                                : 'Now friends';
                            summary.appendChild(confirmation);
                        }
                        refreshFriendsData();
                    })
                    .catch(() => {
                        showStatus('Friend could not be added. Please try again.', true);
                        button.disabled = false;
                        button.classList.remove('is-loading');
                        button.textContent = action === 'accept'
                            ? 'Accept Invite'
                            : action === 'cancel'
                                ? 'Cancel Invite'
                                : 'Invite';
                    });
            });
        });
    }

    function renderRequests(requests) {
        if (!requestsList) {
            return;
        }

        if (!requests.length) {
            requestsList.innerHTML = '<p>No friend invites to approve.</p>';
            return;
        }

        requestsList.innerHTML = requests.map((request) => `
            <article class="friend-request-item">
                ${renderAvatar(request.actor, request.name)}
                <div>
                    <strong>${escapeHtml(request.name)}</strong>
                    <span>${escapeHtml(request.email)}</span>
                </div>
                <div>
                    <button type="button" data-request-id="${request.id}" data-response="accepted">Approve</button>
                    <button type="button" data-request-id="${request.id}" data-response="declined">Decline</button>
                </div>
            </article>
        `).join('');

        requestsList.querySelectorAll('button[data-request-id]').forEach((button) => {
            button.addEventListener('click', () => {
                const response = button.dataset.response;
                const row = button.closest('.friend-request-item');
                button.disabled = true;
                button.classList.add('is-loading');
                button.textContent = response === 'accepted' ? 'Approving...' : 'Declining...';

                postForm(userEndpoint('friend_respond.php'), {
                    csrf_token: csrfToken,
                    request_id: button.dataset.requestId,
                    response
                })
                    .then((data) => {
                        if (!data.ok) {
                            showStatus(data.message || 'Friend invite could not be updated.', true);
                            button.disabled = false;
                            button.classList.remove('is-loading');
                            button.textContent = response === 'accepted' ? 'Approve' : 'Decline';
                            return;
                        }

                        showStatus(data.message || 'Friend invite updated.', false);
                        if (data.xp_reward && window.craftcrawlShowXpReward) {
                            window.craftcrawlShowXpReward(data.xp_reward);
                        }
                        button.classList.remove('is-loading');
                        if (row) {
                            row.remove();
                        }
                        refreshFriendsData();
                    })
                    .catch(() => {
                        showStatus('Friend invite could not be updated.', true);
                        button.disabled = false;
                        button.classList.remove('is-loading');
                        button.textContent = response === 'accepted' ? 'Approve' : 'Decline';
                    });
            });
        });
    }

    function renderCurrentFriends(friends) {
        if (!currentFriendsList) {
            return;
        }

        if (!friends.length) {
            currentFriendsList.innerHTML = '<p>No friends added yet.</p>';
            return;
        }

        const query = (currentFriendsFilter?.value || '').trim().toLowerCase();
        const visibleFriends = query
            ? friends.filter((friend) => String(friend.name || '').toLowerCase().includes(query))
            : friends;

        if (!visibleFriends.length) {
            currentFriendsList.innerHTML = '<p>No friends match that search.</p>';
            return;
        }

        currentFriendsList.innerHTML = visibleFriends.map((friend) => `
            <article class="friend-current-item">
                ${renderAvatar(friend.actor, friend.name)}
                <div class="friend-current-summary">
                    <div class="friend-current-name-row">
                        <strong>${escapeHtml(friend.name)}</strong>
                        ${friend.is_new ? '<span class="friend-current-new-badge">New</span>' : ''}
                    </div>
                    <p class="friend-current-meta">Level ${escapeHtml(friend.level || 1)}${friend.title ? ` &middot; ${escapeHtml(friend.title)}` : ''}</p>
                </div>
                <div class="friend-current-actions">
                    <a href="profile.php?id=${encodeURIComponent(friend.id)}">View Profile</a>
                    <button type="button" class="danger-button friend-remove-button" data-remove-friend-id="${friend.id}" data-remove-friend-name="${escapeHtml(friend.name)}">Remove</button>
                </div>
            </article>
        `).join('');

        currentFriendsList.querySelectorAll('[data-remove-friend-id]').forEach((button) => {
            button.addEventListener('click', () => {
                const friendName = button.dataset.removeFriendName || 'this friend';

                if (!window.confirm(`Remove ${friendName} from your friends?`)) {
                    return;
                }

                button.disabled = true;
                button.classList.add('is-loading');
                button.textContent = 'Removing...';

                postForm(userEndpoint('friend_remove.php'), {
                    csrf_token: csrfToken,
                    friend_id: button.dataset.removeFriendId
                })
                    .then((data) => {
                        if (!data.ok) {
                            showStatus(data.message || 'Friend could not be removed.', true);
                            button.disabled = false;
                            button.classList.remove('is-loading');
                            button.textContent = 'Remove';
                            return;
                        }

                        showStatus(data.message || 'Friend removed.', false);
                        button.classList.remove('is-loading');
                        refreshFriendsData();
                    })
                    .catch(() => {
                        showStatus('Friend could not be removed. Please try again.', true);
                        button.disabled = false;
                        button.classList.remove('is-loading');
                        button.textContent = 'Remove';
                    });
            });
        });
    }

    function renderFeedPollOptions(itemKey, options) {
        const buttons = options.map(function (opt) {
            return '<button type="button" class="business-poll-option-btn" data-feed-poll-vote'
                + ' data-item-key="' + escapeHtml(itemKey) + '" data-option-id="' + opt.id + '">'
                + escapeHtml(opt.option_text) + '</button>';
        }).join('');
        return '<div class="business-poll-vote-options">' + buttons + '</div>';
    }

    function renderFeedPollResults(options, userVotedOptionId, totalVotes) {
        const items = options.map(function (opt) {
            const pct = totalVotes > 0 ? Math.round((opt.vote_count / totalVotes) * 100) : 0;
            const isVoted = opt.id === userVotedOptionId;
            return '<div class="business-poll-result' + (isVoted ? ' is-voted' : '') + '">'
                + '<div class="business-poll-bar-btn">'
                + '<div class="business-poll-bar-fill" style="width:' + pct + '%"></div>'
                + '<span class="business-poll-bar-label">' + escapeHtml(opt.option_text) + '</span>'
                + '</div>'
                + '<span class="business-poll-bar-pct">' + pct + '%</span>'
                + '</div>';
        }).join('');
        return '<div class="business-poll-results" data-poll-results>'
            + items
            + '<p class="business-poll-total">' + totalVotes + ' vote' + (totalVotes !== 1 ? 's' : '') + '</p>'
            + '</div>';
    }

    function renderFeedItem(item) {
        const date = formatDate(item.created_at);
        const actions = renderFeedActions(item);
        const actorName = item.is_self ? 'You' : item.friend_name;

        if (item.type === 'level_up') {
            return `
                <article class="friends-feed-item" ${feedItemAttrs(item)}>
                    ${renderAvatar(item.actor, item.friend_name)}
                    <div class="feed-item-content">
                        ${renderFeedMeta(actorName, date)}
                        <strong class="feed-item-title">Reached Level ${escapeHtml(item.level)}</strong>
                        <p class="feed-item-detail">${escapeHtml(item.title)}</p>
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'badge_earned') {
            return `
                <article class="friends-feed-item" ${feedItemAttrs(item)}>
                    ${renderAvatar(item.actor, item.friend_name)}
                    <div class="feed-item-content">
                        ${renderFeedMeta(actorName, date)}
                        <strong class="feed-item-title">Earned ${escapeHtml(item.badge_name)}</strong>
                        <p class="feed-item-detail">${escapeHtml(item.badge_description)}</p>
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'event_want') {
            return `
                <article class="friends-feed-item" ${feedItemAttrs(item)}>
                    ${renderAvatar(item.actor, item.friend_name)}
                    <div class="feed-item-content">
                        ${renderFeedMeta(actorName, date)}
                        <strong class="feed-item-title">${escapeHtml(item.is_self ? 'Want' : 'Wants')} to go to ${escapeHtml(item.event_name)}</strong>
                        <p class="feed-item-detail">${escapeHtml(item.business_name)} · ${escapeHtml(item.city)}, ${escapeHtml(item.state)}</p>
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'location_want') {
            return `
                <article class="friends-feed-item" ${feedItemAttrs(item)}>
                    ${renderAvatar(item.actor, item.friend_name)}
                    <div class="feed-item-content">
                        ${renderFeedMeta(actorName, date)}
                        <strong class="feed-item-title">${escapeHtml(item.is_self ? 'Want' : 'Wants')} to visit ${escapeHtml(item.business_name)}</strong>
                        <p class="feed-item-detail">${escapeHtml(item.business_type)} · ${escapeHtml(item.city)}, ${escapeHtml(item.state)}</p>
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'business_post') {
            const isPoll = item.post_type === 'poll';
            let pollSection = '';

            if (isPoll) {
                let pollContent = '';
                if (item.user_voted_option_id != null) {
                    pollContent = renderFeedPollResults(item.options || [], item.user_voted_option_id, item.total_votes || 0);
                } else if (item.is_expired) {
                    pollContent = '<p class="business-poll-expiry is-closed">Poll closed</p>';
                } else {
                    pollContent = renderFeedPollOptions(item.item_key, item.options || []);
                }
                pollSection = '<div data-feed-poll-section>' + pollContent + '</div>';
            }

            return `
                <article class="friends-feed-item" ${feedItemAttrs(item)}>
                    <div class="friends-feed-icon">${isPoll ? '📊' : '📢'}</div>
                    <div class="feed-item-content">
                        ${renderFeedMeta(item.business_name, date)}
                        <strong class="feed-item-title">${escapeHtml(item.title)}</strong>
                        ${item.body ? `<p class="feed-item-detail feed-business-post-body">${escapeHtml(item.body)}</p>` : ''}
                        ${pollSection}
                        ${actions}
                    </div>
                </article>
            `;
        }

        return `
            <article class="friends-feed-item" ${feedItemAttrs(item)}>
                ${renderAvatar(item.actor, item.friend_name)}
                <div class="feed-item-content">
                    ${renderFeedMeta(actorName, date)}
                    <strong class="feed-item-title">Visited ${escapeHtml(item.business_name)} for the first time</strong>
                    <p class="feed-item-detail">${escapeHtml(item.city)}, ${escapeHtml(item.state)}</p>
                    ${actions}
                </div>
            </article>
        `;
    }

    function renderFeed(data) {
        const friends = data.friends || [];
        const items = data.feed || [];
        currentFriendsCache = friends;

        if (count) {
            count.textContent = friends.length === 1 ? '1 friend' : `${friends.length} friends`;
        }

        renderCurrentFriends(friends);

        if (!feed) {
            return;
        }

        if (!items.length) {
            feed.innerHTML = friends.length
                ? '<p>No friend activity yet.</p>'
                : '<p>Add friends to see level-ups and first-time visits here.</p>';
            if (sentinel) {
                sentinel.hidden = true;
                feed.appendChild(sentinel);
            }
            return;
        }

        feed.innerHTML = items.map(renderFeedItem).join('');

        if (sentinel) {
            feed.appendChild(sentinel);
        }

        lastItemDate = items[items.length - 1]?.created_at || null;
        hasMore = data.has_more === true;
        if (sentinel) {
            sentinel.hidden = !hasMore;
        }
    }

    // Event delegation for reactions — works for feed lists and feed threads.
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-feed-reaction]');
        if (!button || button.disabled) {
            return;
        }

        const tokenSource = button.closest('[data-csrf-token]');
        const requestCsrfToken = tokenSource?.dataset.csrfToken || csrfToken;

        button.disabled = true;
        button.classList.add('is-loading');

        postForm(userEndpoint('feed_reaction_toggle.php'), {
            csrf_token: requestCsrfToken,
            item_key: button.dataset.itemKey,
            reaction_type: button.dataset.reactionType
        })
            .then((data) => {
                if (!data.ok) {
                    showStatus(data.message || 'Reaction could not be saved.', true);
                    return;
                }

                if (data.xp_reward && window.craftcrawlShowXpReward) {
                    window.craftcrawlShowXpReward(data.xp_reward);
                }

                const article = button.closest('article');
                const reactionsDiv = button.closest('.feed-reactions');
                if (reactionsDiv && data.reactions) {
                    const itemKey = button.dataset.itemKey;
                    const itemType = article?.dataset.feedItemType || itemKey.split(':')[0];
                    const availableReactions = availableReactionTypes({
                        type: itemType,
                        is_self: article?.dataset.feedIsSelf === 'true'
                    });
                    const reactionMap = {};
                    data.reactions.forEach((r) => { reactionMap[r.type] = r; });
                    reactionsDiv.innerHTML = availableReactions.map((type) => {
                        const reaction = reactionMap[type] || { count: 0, reacted: false };
                        return `<button type="button" class="${reaction.reacted ? 'is-active' : ''}" data-feed-reaction data-item-key="${escapeHtml(itemKey)}" data-reaction-type="${type}">${reactionLabels[type]}${reaction.count > 0 ? ` ${reaction.count}` : ''}</button>`;
                    }).join('');
                }
            })
            .catch((error) => showStatus(error.message || 'Reaction could not be saved.', true))
            .finally(() => {
                button.disabled = false;
                button.classList.remove('is-loading');
            });
    });

    // Delegated poll vote handler for feed items
    if (feed) {
        feed.addEventListener('click', function (event) {
            const voteBtn = event.target.closest('[data-feed-poll-vote]');
            if (!voteBtn || voteBtn.disabled) {
                return;
            }

            const pollSection = voteBtn.closest('[data-feed-poll-section]');
            if (!pollSection) {
                return;
            }

            pollSection.querySelectorAll('[data-feed-poll-vote]').forEach(function (btn) {
                btn.disabled = true;
            });

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('post_id', voteBtn.dataset.itemKey.split(':')[1]);
            formData.append('option_id', voteBtn.dataset.optionId);

            fetch(userEndpoint('business_poll_vote.php'), {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        pollSection.querySelectorAll('[data-feed-poll-vote]').forEach(function (btn) {
                            btn.disabled = false;
                        });
                        return;
                    }
                    pollSection.innerHTML = renderFeedPollResults(
                        data.options,
                        data.user_voted_option_id,
                        data.total_votes
                    );
                })
                .catch(function () {
                    pollSection.querySelectorAll('[data-feed-poll-vote]').forEach(function (btn) {
                        btn.disabled = false;
                    });
                });
        });
    }

    function loadMore() {
        if (!feed || !lastItemDate || !hasMore || loadingMore) {
            return;
        }

        loadingMore = true;

        fetch(`${userEndpoint('friends_feed.php')}?before=${encodeURIComponent(lastItemDate)}`, { credentials: 'same-origin' })
            .then((r) => r.json())
            .then((data) => {
                if (!data.ok || !data.feed || !data.feed.length) {
                    hasMore = false;
                    if (sentinel) {
                        sentinel.hidden = true;
                    }
                    return;
                }

                data.feed.forEach((item) => {
                    const div = document.createElement('div');
                    div.innerHTML = renderFeedItem(item).trim();
                    const article = div.firstElementChild;
                    if (article) {
                        if (sentinel && sentinel.parentNode === feed) {
                            feed.insertBefore(article, sentinel);
                        } else {
                            feed.appendChild(article);
                        }
                    }
                });

                lastItemDate = data.feed[data.feed.length - 1].created_at;
                hasMore = data.has_more === true;
                if (sentinel) {
                    sentinel.hidden = !hasMore;
                }
            })
            .catch(() => {})
            .finally(() => {
                loadingMore = false;
            });
    }

    if (sentinel) {
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                loadMore();
            }
        }, { threshold: 0.1 });
        observer.observe(sentinel);
    }

    function renderFeedActions(item) {
        if (!item.item_key) {
            return '';
        }

        if (item.allow_interactions === false && item.type !== 'business_post') {
            return '';
        }

        const reactions = item.reactions || [];
        const reactionMap = {};
        const availableReactions = availableReactionTypes(item);

        reactions.forEach((reaction) => {
            reactionMap[reaction.type] = reaction;
        });

        return `
            <div class="feed-action-row">
                <div class="feed-primary-actions">
                    ${renderCommentsLink(item)}
                    ${renderFeedDetailLink(item)}
                </div>
                <div class="feed-reactions">
                    ${availableReactions.map((type) => {
                        const reaction = reactionMap[type] || { count: 0, reacted: false };
                        return `
                            <button type="button" class="${reaction.reacted ? 'is-active' : ''}" data-feed-reaction data-item-key="${escapeHtml(item.item_key)}" data-reaction-type="${type}">
                                ${reactionLabels[type]}${reaction.count > 0 ? ` ${reaction.count}` : ''}
                            </button>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }

    function renderCommentsLink(item) {
        if (!item.item_key) {
            return '';
        }

        const commentCount = Number(item.comment_count || 0);
        const label = commentCount > 0 ? String(commentCount) : '';

        return `
            <a class="feed-comments-link" href="feed_post.php?item=${encodeURIComponent(item.item_key)}" aria-label="Show comments">
                <svg aria-hidden="true" viewBox="0 0 24 24">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5a8.5 8.5 0 0 1 17 0Z"></path>
                </svg>
                ${label ? `<span>${label}</span>` : ''}
            </a>
        `;
    }

    function renderFeedDetailLink(item) {
        if (item.type === 'event_want') {
            return `<a class="feed-detail-link" href="../event_details.php?id=${encodeURIComponent(item.event_id)}&date=${encodeURIComponent(item.event_date)}">View Event</a>`;
        }

        if (item.type === 'first_visit' || item.type === 'location_want' || item.type === 'business_post') {
            return `<a class="feed-detail-link" href="../business_details.php?id=${encodeURIComponent(item.business_id)}">View Business</a>`;
        }

        return '';
    }

    function loadFeed() {
        return fetch(userEndpoint('friends_feed.php'), { credentials: 'same-origin' })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) {
                    showStatus(data.message || 'Friends feed could not be loaded.', true);
                    return;
                }

                renderFeed(data);
                loadStatus();
            })
            .catch(() => {
                showStatus('Friends feed could not be loaded.', true);
            });
    }

    window.addEventListener('craftcrawl:event-want-updated', () => {
        loadFeed();
    });

    function loadRequests() {
        if (!requestsList) {
            return Promise.resolve();
        }

        return fetch(userEndpoint('friend_requests.php'), { credentials: 'same-origin' })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) {
                    showStatus(data.message || 'Friend invites could not be loaded.', true);
                    return;
                }

                renderRequests(data.requests || []);
            })
            .catch(() => {
                showStatus('Friend invites could not be loaded.', true);
            });
    }

    function refreshFriendsData() {
        loadRequests();
        return loadFeed()
            .then(() => loadStatus())
            .then(() => {
                if (managerPage && currentStatus.newFriends > 0) {
                    return markFriendsSeen();
                }

                return null;
            });
    }

    function runFriendSearch(query, { showShortQueryMessage = false } = {}) {
        const requestId = ++friendSearchRequestId;

        if (query.length < 2) {
            if (results) {
                results.innerHTML = '';
                results.hidden = true;
            }
            if (showShortQueryMessage) {
                showStatus('Search by at least two characters.', true);
            }
            return Promise.resolve();
        }

        return fetch(`${userEndpoint('friend_search.php')}?q=${encodeURIComponent(query)}`, { credentials: 'same-origin' })
                .then((response) => response.json())
                .then((data) => {
                    if (requestId !== friendSearchRequestId) {
                        return;
                    }

                    if (!data.ok) {
                        showStatus(data.message || 'Search failed.', true);
                        return;
                    }

                    renderSearchResults(data.users || []);
                })
                .catch(() => {
                    if (requestId === friendSearchRequestId) {
                        showStatus('Search failed. Please try again.', true);
                    }
                });
    }

    if (form && input) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            hideStatus();
            window.clearTimeout(friendSearchTimer);
            runFriendSearch(input.value.trim(), { showShortQueryMessage: true });
        });

        input.addEventListener('input', () => {
            hideStatus();
            window.clearTimeout(friendSearchTimer);

            const query = input.value.trim();
            friendSearchTimer = window.setTimeout(() => {
                runFriendSearch(query);
            }, 250);
        });
    }

    if (currentFriendsFilter) {
        currentFriendsFilter.addEventListener('input', () => renderCurrentFriends(currentFriendsCache));
    }

    recommendationButtons.forEach((button) => {
        button.addEventListener('click', () => {
            button.disabled = true;
            button.classList.add('is-loading');
            postForm(userEndpoint('recommendation_update.php'), {
                csrf_token: csrfToken,
                recommendation_id: button.dataset.recommendationId,
                status: button.dataset.recommendationStatus
            })
                .then((data) => {
                    if (!data.ok) {
                        showStatus(data.message || 'Recommendation could not be updated.', true);
                        button.disabled = false;
                        button.classList.remove('is-loading');
                        return;
                    }

                    button.closest('.friend-recommendation-card')?.remove();
                    loadStatus();
                })
                .catch(() => {
                    showStatus('Recommendation could not be updated.', true);
                    button.disabled = false;
                    button.classList.remove('is-loading');
                });
        });
    });

    if (managerPage) {
        refreshFriendsData();
    } else if (panel) {
        loadRequests();
        loadFeed()
            .then(() => markFriendsSeen());
    } else {
        loadStatus();
    }
};
window.CraftCrawlInitFriends();
