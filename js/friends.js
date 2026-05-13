(function () {
    const panel = document.querySelector('[data-friends-panel]');
    const managerPage = document.querySelector('[data-friends-manager-page]');
    const root = managerPage || document;
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
        badgeCount: 0
    };
    let currentFriendsCache = [];
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

    if (!panel && !managerPage && !menuBadge && !tabBadge) {
        return;
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
        }).then((response) => response.json());
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

    function loadStatus() {
        return fetch('friend_status.php', { credentials: 'same-origin' })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) {
                    return;
                }

                const badgeCount = Number(data.badge_count || 0);
                currentStatus = {
                    pendingInvites: Number(data.pending_invites || 0),
                    newFriends: Number(data.new_friends || 0),
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

        return postForm('friend_seen.php', {
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

            if (user.is_friend) {
                buttonText = 'Added';
                disabled = 'disabled';
            } else if (user.pending_sent) {
                buttonText = 'Invite Sent';
                disabled = 'disabled';
            } else if (user.pending_received) {
                buttonText = 'Accept Invite';
                action = 'accept';
            }

            return `
                <article class="friend-search-result">
                    <div>
                        <strong>${escapeHtml(user.name)}</strong>
                        <span>${escapeHtml(user.email)}</span>
                    </div>
                    <button type="button" data-friend-id="${user.id}" data-request-id="${user.received_request_id || ''}" data-friend-action="${action}" ${disabled}>
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
                button.textContent = action === 'accept' ? 'Accepting...' : 'Sending...';

                const request = action === 'accept'
                    ? postForm('friend_respond.php', {
                        csrf_token: csrfToken,
                        request_id: button.dataset.requestId,
                        response: 'accepted'
                    })
                    : postForm('friend_add.php', {
                        csrf_token: csrfToken,
                        friend_id: button.dataset.friendId
                    });

                request
                    .then((data) => {
                        if (!data.ok) {
                            showStatus(data.message || 'Friend could not be added.', true);
                            button.disabled = false;
                            button.classList.remove('is-loading');
                            button.textContent = action === 'accept' ? 'Accept Invite' : 'Invite';
                            return;
                        }

                        showStatus(data.message || 'Friend invite updated.', false);
                        button.classList.remove('is-loading');
                        button.textContent = data.status === 'pending' ? 'Invite Sent' : 'Added';
                        refreshFriendsData();
                    })
                    .catch(() => {
                        showStatus('Friend could not be added. Please try again.', true);
                        button.disabled = false;
                        button.classList.remove('is-loading');
                        button.textContent = action === 'accept' ? 'Accept Invite' : 'Invite';
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

                postForm('friend_respond.php', {
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
                <div>
                    <strong>${escapeHtml(friend.name)}</strong>
                    ${friend.is_new ? '<span>New</span>' : ''}
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

                postForm('friend_remove.php', {
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

    function renderFeedItem(item) {
        const date = formatDate(item.created_at);
        const actions = renderFeedActions(item);

        if (item.type === 'level_up') {
            return `
                <article class="friends-feed-item">
                    <div class="friends-feed-icon">🎉</div>
                    <div>
                        <strong>${escapeHtml(item.friend_name)} reached Level ${escapeHtml(item.level)}</strong>
                        <p>${escapeHtml(item.title)}${date ? ` · ${escapeHtml(date)}` : ''}</p>
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'badge_earned') {
            return `
                <article class="friends-feed-item">
                    <div class="friends-feed-icon">🏅</div>
                    <div>
                        <strong>${escapeHtml(item.friend_name)} earned ${escapeHtml(item.badge_name)}</strong>
                        <p>${escapeHtml(item.badge_description)}${date ? ` · ${escapeHtml(date)}` : ''}</p>
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'event_want') {
            return `
                <article class="friends-feed-item">
                    <div class="friends-feed-icon">📍</div>
                    <div>
                        <strong>${escapeHtml(item.friend_name)} wants to go to ${escapeHtml(item.event_name)}</strong>
                        <p>${escapeHtml(item.business_name)} · ${escapeHtml(item.city)}, ${escapeHtml(item.state)}</p>
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'location_want') {
            return `
                <article class="friends-feed-item">
                    <div class="friends-feed-icon">🔖</div>
                    <div>
                        <strong>${escapeHtml(item.friend_name)} wants to visit ${escapeHtml(item.business_name)}</strong>
                        <p>${escapeHtml(item.business_type)} · ${escapeHtml(item.city)}, ${escapeHtml(item.state)}${date ? ` · ${escapeHtml(date)}` : ''}</p>
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'business_post') {
            const isPoll = item.post_type === 'poll';
            return `
                <article class="friends-feed-item">
                    <div class="friends-feed-icon">${isPoll ? '📊' : '📢'}</div>
                    <div>
                        <strong>${escapeHtml(item.business_name)}</strong>
                        <p>${escapeHtml(item.title)}${date ? ` · ${escapeHtml(date)}` : ''}</p>
                        ${item.body ? `<p class="feed-announcement-body">${escapeHtml(item.body)}</p>` : ''}
                        ${actions}
                    </div>
                </article>
            `;
        }

        return `
            <article class="friends-feed-item">
                <div class="friends-feed-icon">1st</div>
                <div>
                    <strong>${escapeHtml(item.friend_name)} visited ${escapeHtml(item.business_name)} for the first time</strong>
                    <p>${escapeHtml(item.city)}, ${escapeHtml(item.state)}${date ? ` · ${escapeHtml(date)}` : ''}</p>
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

    // Event delegation for reactions — works for initial and paginated items
    if (feed) {
        feed.addEventListener('click', (event) => {
            const button = event.target.closest('[data-feed-reaction]');
            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.classList.add('is-loading');

            postForm('feed_reaction_toggle.php', {
                csrf_token: csrfToken,
                item_key: button.dataset.itemKey,
                reaction_type: button.dataset.reactionType
            })
                .then((data) => {
                    if (!data.ok) {
                        showStatus(data.message || 'Reaction could not be saved.', true);
                        return;
                    }

                    // Update reaction buttons in place without reloading the full feed
                    const article = button.closest('article');
                    const reactionsDiv = article?.querySelector('.feed-reactions');
                    if (reactionsDiv && data.reactions) {
                        const itemKey = button.dataset.itemKey;
                        const itemType = itemKey.split(':')[0];
                        const availableReactions = reactionTypesByItemType[itemType] || Object.keys(reactionLabels);
                        const reactionMap = {};
                        data.reactions.forEach((r) => { reactionMap[r.type] = r; });
                        reactionsDiv.innerHTML = availableReactions.map((type) => {
                            const reaction = reactionMap[type] || { count: 0, reacted: false };
                            return `<button type="button" class="${reaction.reacted ? 'is-active' : ''}" data-feed-reaction data-item-key="${escapeHtml(itemKey)}" data-reaction-type="${type}">${reactionLabels[type]}${reaction.count > 0 ? ` ${reaction.count}` : ''}</button>`;
                        }).join('');
                    }
                })
                .catch(() => showStatus('Reaction could not be saved.', true))
                .finally(() => {
                    button.disabled = false;
                    button.classList.remove('is-loading');
                });
        });
    }

    function loadMore() {
        if (!feed || !lastItemDate || !hasMore || loadingMore) {
            return;
        }

        loadingMore = true;

        fetch(`friends_feed.php?before=${encodeURIComponent(lastItemDate)}`, { credentials: 'same-origin' })
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

        const reactions = item.reactions || [];
        const reactionMap = {};
        const availableReactions = reactionTypesByItemType[item.type] || Object.keys(reactionLabels);

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
                <span aria-hidden="true">💬</span>
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
        return fetch('friends_feed.php', { credentials: 'same-origin' })
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

        return fetch('friend_requests.php', { credentials: 'same-origin' })
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

    if (form && input) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            hideStatus();

            const query = input.value.trim();
            const searchButton = form.querySelector('button[type="submit"]');

            if (query.length < 2) {
                showStatus('Search by at least two characters.', true);
                return;
            }

            if (searchButton) {
                searchButton.disabled = true;
                searchButton.classList.add('is-loading');
            }

            fetch(`friend_search.php?q=${encodeURIComponent(query)}`, { credentials: 'same-origin' })
                .then((response) => response.json())
                .then((data) => {
                    if (!data.ok) {
                        showStatus(data.message || 'Search failed.', true);
                        return;
                    }

                    renderSearchResults(data.users || []);
                })
                .catch(() => {
                    showStatus('Search failed. Please try again.', true);
                })
                .finally(() => {
                    if (searchButton) {
                        searchButton.disabled = false;
                        searchButton.classList.remove('is-loading');
                    }
                });
        });
    }

    if (currentFriendsFilter) {
        currentFriendsFilter.addEventListener('input', () => renderCurrentFriends(currentFriendsCache));
    }

    recommendationButtons.forEach((button) => {
        button.addEventListener('click', () => {
            button.disabled = true;
            button.classList.add('is-loading');
            postForm('recommendation_update.php', {
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
            .then(() => loadStatus())
            .then(() => {
                if (currentStatus.newFriends > 0) {
                    return markFriendsSeen();
                }

                return null;
            });
    } else {
        loadStatus();
    }
}());
