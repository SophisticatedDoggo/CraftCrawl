(function () {
    const panel = document.querySelector('[data-friends-panel]');
    const manager = document.querySelector('[data-friends-manager]');

    if (!panel || !manager) {
        return;
    }

    const form = manager.querySelector('[data-friends-search-form]');
    const input = manager.querySelector('#friend-search-input');
    const results = manager.querySelector('[data-friends-search-results]');
    const requestsList = manager.querySelector('[data-friend-requests-list]');
    const currentFriendsList = manager.querySelector('[data-current-friends-list]');
    const managerToggles = document.querySelectorAll('[data-friends-manager-toggle]');
    const managerClose = manager.querySelector('[data-friends-manager-close]');
    const feed = panel.querySelector('[data-friends-feed]');
    const status = manager.querySelector('[data-friends-status]');
    const count = panel.querySelector('[data-friends-count]');
    const menuBadge = document.querySelector('[data-friends-menu-badge]');
    const tabBadge = document.querySelector('[data-friends-tab-badge]');
    const csrfToken = panel.dataset.csrfToken || '';
    let currentStatus = {
        pendingInvites: 0,
        newFriends: 0,
        badgeCount: 0
    };

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

    function renderSearchResults(users) {
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
                        loadFeed();
                        loadRequests();
                        loadStatus();
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
                        loadFeed();
                        loadRequests();
                        loadStatus();
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
                <button type="button" class="danger-button friend-remove-button" data-remove-friend-id="${friend.id}" data-remove-friend-name="${escapeHtml(friend.name)}">Remove</button>
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
                        loadFeed();
                        loadRequests();
                        loadStatus();
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

        if (!items.length) {
            feed.innerHTML = friends.length
                ? '<p>No friend activity yet.</p>'
                : '<p>Add friends to see level-ups and first-time visits here.</p>';
            return;
        }

        feed.innerHTML = items.map((item) => {
            const date = formatDate(item.created_at);

            if (item.type === 'level_up') {
                return `
                    <article class="friends-feed-item">
                        <div class="friends-feed-icon">LV</div>
                        <div>
                            <strong>${escapeHtml(item.friend_name)} reached Level ${escapeHtml(item.level)}</strong>
                            <p>${escapeHtml(item.title)}${date ? ` · ${escapeHtml(date)}` : ''}</p>
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
                    </div>
                </article>
            `;
        }).join('');
    }

    function loadFeed() {
        fetch('friends_feed.php', { credentials: 'same-origin' })
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
        fetch('friend_requests.php', { credentials: 'same-origin' })
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

    function setBadge(element, value) {
        if (!element) {
            return;
        }

        element.textContent = value > 9 ? '9+' : String(value);
        element.hidden = value < 1;
    }

    function loadStatus() {
        fetch('friend_status.php', { credentials: 'same-origin' })
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
        postForm('friend_seen.php', {
            csrf_token: csrfToken
        })
            .then(() => loadStatus())
            .catch(() => {});
    }

    function openManager() {
        manager.hidden = false;
        loadRequests();
        loadStatus();
        if (currentStatus.newFriends > 0) {
            markFriendsSeen();
        }
        if (input) {
            input.focus();
        }
    }

    function closeManager() {
        manager.hidden = true;
    }

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

    managerToggles.forEach((button) => {
        button.addEventListener('click', openManager);
    });

    document.querySelectorAll('[data-tab="friends-panel"], [data-app-tab="friends-panel"]').forEach((button) => {
        button.addEventListener('click', () => {
            if (currentStatus.badgeCount > 0) {
                openManager();
                return;
            }
        });
    });

    if (managerClose) {
        managerClose.addEventListener('click', closeManager);
    }

    manager.addEventListener('click', (event) => {
        if (event.target === manager) {
            closeManager();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !manager.hidden) {
            closeManager();
        }
    });

    loadRequests();
    loadStatus();
    loadFeed();
}());
