(function () {
    const panel = document.querySelector('[data-friends-panel]');
    const managerPage = document.querySelector('[data-friends-manager-page]');
    const root = managerPage || document;
    const form = root.querySelector('[data-friends-search-form]');
    const input = root.querySelector('#friend-search-input');
    const results = root.querySelector('[data-friends-search-results]');
    const requestsList = root.querySelector('[data-friend-requests-list]');
    const currentFriendsList = root.querySelector('[data-current-friends-list]');
    const recommendationButtons = root.querySelectorAll('[data-recommendation-id]');
    const feed = panel?.querySelector('[data-friends-feed]');
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
    const reactionLabels = {
        cheers: '🍻 Cheers',
        nice_find: '🔥 Nice',
        want_to_go: '📍 Want to Go'
    };
    const reactionTypesByItemType = {
        first_visit: ['cheers', 'nice_find', 'want_to_go'],
        level_up: ['cheers', 'nice_find']
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
                            button.textContent = action === 'accept' ? 'Accept Invite' : 'Invite';
                            return;
                        }

                        showStatus(data.message || 'Friend invite updated.', false);
                        button.textContent = data.status === 'pending' ? 'Invite Sent' : 'Added';
                        refreshFriendsData();
                    })
                    .catch(() => {
                        showStatus('Friend could not be added. Please try again.', true);
                        button.disabled = false;
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
                            button.textContent = response === 'accepted' ? 'Approve' : 'Decline';
                            return;
                        }

                        showStatus(data.message || 'Friend invite updated.', false);
                        if (row) {
                            row.remove();
                        }
                        refreshFriendsData();
                    })
                    .catch(() => {
                        showStatus('Friend invite could not be updated.', true);
                        button.disabled = false;
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

        currentFriendsList.innerHTML = friends.map((friend) => `
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
                button.textContent = 'Removing...';

                postForm('friend_remove.php', {
                    csrf_token: csrfToken,
                    friend_id: button.dataset.removeFriendId
                })
                    .then((data) => {
                        if (!data.ok) {
                            showStatus(data.message || 'Friend could not be removed.', true);
                            button.disabled = false;
                            button.textContent = 'Remove';
                            return;
                        }

                        showStatus(data.message || 'Friend removed.', false);
                        refreshFriendsData();
                    })
                    .catch(() => {
                        showStatus('Friend could not be removed. Please try again.', true);
                        button.disabled = false;
                        button.textContent = 'Remove';
                    });
            });
        });
    }

    function renderFeed(data) {
        const friends = data.friends || [];
        const items = data.feed || [];

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
            return;
        }

        feed.innerHTML = items.map((item) => {
            const date = formatDate(item.created_at);
            const reactions = renderReactions(item);

            if (item.type === 'level_up') {
                return `
                    <article class="friends-feed-item">
                        <div class="friends-feed-icon">LV</div>
                        <div>
                            <strong>${escapeHtml(item.friend_name)} reached Level ${escapeHtml(item.level)}</strong>
                            <p>${escapeHtml(item.title)}${date ? ` · ${escapeHtml(date)}` : ''}</p>
                            ${reactions}
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
                        <a href="../business_details.php?id=${encodeURIComponent(item.business_id)}">View business</a>
                        ${reactions}
                    </div>
                </article>
            `;
        }).join('');

        feed.querySelectorAll('[data-feed-reaction]').forEach((button) => {
            button.addEventListener('click', () => {
                button.disabled = true;
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

                        loadFeed();
                    })
                    .catch(() => showStatus('Reaction could not be saved.', true))
                    .finally(() => {
                        button.disabled = false;
                    });
            });
        });

        feed.querySelectorAll('[data-reaction-details-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = feed.querySelector(`#${button.getAttribute('aria-controls')}`);
                const isExpanded = button.getAttribute('aria-expanded') === 'true';

                button.setAttribute('aria-expanded', String(!isExpanded));
                button.querySelector('[data-reaction-toggle-arrow]').textContent = isExpanded ? '>' : 'v';
                button.querySelector('[data-reaction-toggle-label]').textContent = isExpanded ? 'Show Reactions' : 'Hide Reactions';

                if (target) {
                    target.hidden = isExpanded;
                }
            });
        });
    }

    function renderReactions(item) {
        if (!item.item_key) {
            return '';
        }

        const reactions = item.reactions || [];
        const reactionMap = {};
        const availableReactions = reactionTypesByItemType[item.type] || Object.keys(reactionLabels);

        reactions.forEach((reaction) => {
            reactionMap[reaction.type] = reaction;
        });

        const commentsLink = renderCommentsLink(item);
        const summary = renderReactionSummary(availableReactions, reactionMap, item.item_key);

        return `
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
            ${commentsLink}
            ${summary}
        `;
    }

    function renderCommentsLink(item) {
        if (!item.item_key) {
            return '';
        }

        const commentCount = Number(item.comment_count || 0);
        const label = commentCount === 1 ? 'Show Comments (1)' : `Show Comments${commentCount > 0 ? ` (${commentCount})` : ''}`;

        return `
            <a class="feed-comments-link" href="feed_post.php?item=${encodeURIComponent(item.item_key)}">
                <span aria-hidden="true">💬</span>
                <span>${label}</span>
            </a>
        `;
    }

    function renderReactionSummary(availableReactions, reactionMap, itemKey) {
        const rows = availableReactions
            .map((type) => {
                const reaction = reactionMap[type];
                const reactors = reaction?.reactors || [];

                if (!reactors.length) {
                    return '';
                }

                const shownReactors = reactors.slice(0, 3);
                const remaining = reactors.length - shownReactors.length;
                const rows = shownReactors.map((reactor) => `
                    <span>
                        <strong>${reactionLabels[type]}</strong>
                        <span aria-hidden="true">-</span>
                        ${escapeHtml(reactor.name || 'Someone')}
                    </span>
                `);

                if (remaining > 0) {
                    rows.push(`
                        <span>
                            <strong>${reactionLabels[type]}</strong>
                            <span aria-hidden="true">-</span>
                            +${remaining} more
                        </span>
                    `);
                }

                return rows.join('');
            })
            .filter(Boolean);

        if (!rows.length) {
            return '';
        }

        const summaryId = `reaction-details-${escapeHtml(itemKey).replace(/[^a-zA-Z0-9_-]/g, '-')}`;

        return `
            <button type="button" class="reaction-details-toggle" data-reaction-details-toggle aria-expanded="false" aria-controls="${summaryId}">
                <span data-reaction-toggle-arrow aria-hidden="true">&gt;</span>
                <span data-reaction-toggle-label>Show Reactions</span>
            </button>
            <div class="feed-reaction-summary" id="${summaryId}" hidden>
                ${rows.join('')}
            </div>
        `;
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

            if (query.length < 2) {
                showStatus('Search by at least two characters.', true);
                return;
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
                });
        });
    }

    recommendationButtons.forEach((button) => {
        button.addEventListener('click', () => {
            button.disabled = true;
            postForm('recommendation_update.php', {
                csrf_token: csrfToken,
                recommendation_id: button.dataset.recommendationId,
                status: button.dataset.recommendationStatus
            })
                .then((data) => {
                    if (!data.ok) {
                        showStatus(data.message || 'Recommendation could not be updated.', true);
                        button.disabled = false;
                        return;
                    }

                    button.closest('.friend-recommendation-card')?.remove();
                })
                .catch(() => {
                    showStatus('Recommendation could not be updated.', true);
                    button.disabled = false;
                });
        });
    });

    document.querySelectorAll('[data-tab="friends-panel"], [data-app-tab="friends-panel"]').forEach((button) => {
        button.addEventListener('click', () => {
            if (currentStatus.badgeCount > 0) {
                window.location.href = 'friends.php';
            }
        });
    });

    if (managerPage) {
        refreshFriendsData();
    } else {
        loadRequests();
        loadFeed();
        loadStatus();
    }
}());
