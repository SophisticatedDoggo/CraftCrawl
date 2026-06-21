window.CraftCrawlInitQuestChains = function (root) {
    'use strict';

    var panel = (root || document).querySelector('[data-quest-chains-panel]');
    if (!panel) return;
    if (panel.dataset.questChainsReady === 'true') return;
    panel.dataset.questChainsReady = 'true';

    var csrfToken = panel.dataset.csrfToken || window.CRAFTCRAWL_CSRF_TOKEN || '';
    var chainsTabLoaded = false;

    var subtabs = panel.querySelectorAll('[data-quest-subtab]');
    var subtabPanels = panel.querySelectorAll('[data-quest-subtab-panel]');

    subtabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = tab.dataset.questSubtab;

            subtabs.forEach(function (t) {
                t.classList.toggle('is-active', t === tab);
                t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
            });

            subtabPanels.forEach(function (p) {
                p.hidden = p.dataset.questSubtabPanel !== target;
            });

            if (target === 'chains' && !chainsTabLoaded) {
                chainsTabLoaded = true;
                loadChainsTab();
            }
        });
    });

    function loadChainsTab() {
        var container = panel.querySelector('[data-chain-content]');
        if (!container) return;

        container.innerHTML = '<p class="chain-status-message">Loading quest chains...</p>';

        postJSON('quest_chain_status.php', {})
            .then(function (data) {
                if (!data.ok) {
                    container.innerHTML = '<p class="chain-status-message">Could not load quest chains.</p>';
                    return;
                }

                renderChainsTab(container, data);
            })
            .catch(function () {
                container.innerHTML = '<p class="chain-status-message">Could not load quest chains.</p>';
                chainsTabLoaded = false;
            });
    }

    function renderChainsTab(container, data) {
        var html = '';

        if (data.pending_invites && data.pending_invites.length > 0) {
            html += '<div class="chain-invites" data-chain-invites>';
            html += '<h3 class="chain-section-heading">Pending Invites</h3>';
            data.pending_invites.forEach(function (invite) {
                html += '<article class="chain-invite-card" data-chain-invite="' + invite.chain_id + '">' +
                    '<div class="chain-invite-header">' +
                        '<strong>' + escapeHtml(invite.chain_name) + '</strong>' +
                        '<small>from ' + escapeHtml(invite.invited_by.name) + '</small>' +
                    '</div>' +
                    '<p>' + escapeHtml(invite.chain_description) + '</p>' +
                    '<small>' + invite.step_count + ' steps · +' + invite.xp_reward + ' XP</small>' +
                    '<div class="chain-invite-actions">' +
                        '<button type="button" class="chain-btn-primary" data-chain-invite-accept="' + invite.chain_id + '">Accept</button>' +
                        '<button type="button" class="chain-btn-secondary" data-chain-invite-decline="' + invite.chain_id + '">Decline</button>' +
                    '</div>' +
                '</article>';
            });
            html += '</div>';
        }

        if (data.active_chain) {
            var chain = data.active_chain;
            html += '<div class="chain-active" data-chain-active>';
            html += '<h3 class="chain-section-heading">Active Quest Chain</h3>';
            html += '<article class="chain-card is-active">';
            html += '<div class="chain-card-header"><div>';
            html += '<strong>' + escapeHtml(chain.name) + '</strong>';
            html += '<p>' + escapeHtml(chain.description) + '</p>';
            html += '</div>';
            html += '<span class="chain-xp-badge">+' + chain.xp_reward + ' XP</span>';
            html += '</div>';
            html += '<div class="chain-progress-bar"><span style="width:' + chain.progress_percent + '%;"></span></div>';
            html += '<small class="chain-progress-label">' + chain.completed_count + ' / ' + chain.step_count + ' steps complete</small>';
            html += '<ol class="chain-steps-list">';

            (chain.steps || []).forEach(function (step) {
                var completeClass = step.completed ? ' is-complete' : '';
                var actionLabels = { checkin: 'Check in', review: 'Leave a review', event_want_to_go: 'RSVP to an event', feed_reaction: 'React to a post' };
                var actionLabel = actionLabels[step.action_type] || step.action_type;
                var locationInfo = escapeHtml(step.location_name);
                if (step.location_city) locationInfo += ' · ' + escapeHtml(step.location_city);

                html += '<li class="chain-step' + completeClass + '">';
                html += '<span class="chain-step-dot" aria-hidden="true"></span>';
                html += '<div class="chain-step-content">';
                html += '<strong>' + escapeHtml(actionLabel) + '</strong>';
                html += '<span>' + locationInfo + '</span>';
                html += '</div>';
                if (step.completed) {
                    html += '<span class="chain-step-check" aria-label="Completed"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8.5L6.5 12L13 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
                }
                html += '</li>';
            });

            html += '</ol>';

            if (data.members && data.members.length > 1) {
                html += '<div class="chain-member-avatars">';
                data.members.forEach(function (member) {
                    if (member.profile_photo_url) {
                        html += '<img class="chain-member-avatar" src="' + escapeHtml(member.profile_photo_url) + '" alt="' + escapeHtml(member.name) + '" title="' + escapeHtml(member.name) + ' (' + member.completed_count + '/' + chain.step_count + ')">';
                    } else {
                        var initials = (member.name || '?').charAt(0).toUpperCase();
                        html += '<span class="chain-member-avatar-placeholder" title="' + escapeHtml(member.name) + ' (' + member.completed_count + '/' + chain.step_count + ')">' + initials + '</span>';
                    }
                });
                html += '</div>';
            }

            html += '<div class="chain-actions">';
            if (chain.is_owner) {
                html += '<button type="button" data-chain-invite-friends="' + chain.id + '" class="chain-btn-primary">Invite Friends</button>';
            }
            html += '<button type="button" data-chain-abandon="' + chain.id + '" class="chain-btn-danger">' + (chain.is_owner ? 'Abandon Quest' : 'Leave Quest') + '</button>';
            html += '</div>';
            html += '</article></div>';
        } else {
            html += '<div class="chain-discover" data-chain-discover>';
            html += '<div class="chain-discover-header">';
            html += '<h3 class="chain-section-heading">Quest Chains</h3>';
            html += '<p>Multi-step adventures through locations near you. Check in, leave reviews, and explore new spots to earn bonus XP.</p>';
            html += '</div>';
            html += '<div class="chain-options" data-chain-options></div>';
            html += '<div class="chain-discover-actions">';
            html += '<button type="button" data-chain-generate class="chain-btn-primary">Discover Quest Chains</button>';
            html += '</div>';
            html += '<p class="chain-status-message" data-chain-status hidden></p>';
            html += '</div>';
        }

        container.innerHTML = html;
        bindChainActions(container);
    }

    function bindChainActions(container) {
        container.querySelectorAll('[data-chain-abandon]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Abandon this quest chain? All progress will be lost.')) return;
                var chainId = parseInt(btn.dataset.chainAbandon, 10);
                postJSON('quest_chain_abandon.php', { chain_id: chainId })
                    .then(function (data) {
                        if (!data.ok) { alert(data.message || 'Could not abandon quest chain.'); return; }
                        chainsTabLoaded = false;
                        loadChainsTab();
                    })
                    .catch(function () { alert('Failed to abandon quest chain.'); });
            });
        });

        container.querySelectorAll('[data-chain-invite-accept]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                respondToInvite(parseInt(btn.dataset.chainInviteAccept, 10), true, btn);
            });
        });

        container.querySelectorAll('[data-chain-invite-decline]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                respondToInvite(parseInt(btn.dataset.chainInviteDecline, 10), false, btn);
            });
        });

        container.querySelectorAll('[data-chain-invite-friends]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openInviteModal(parseInt(btn.dataset.chainInviteFriends, 10));
            });
        });

        var generateBtn = container.querySelector('[data-chain-generate]');
        var optionsContainer = container.querySelector('[data-chain-options]');
        var statusEl = container.querySelector('[data-chain-status]');

        if (generateBtn) {
            generateBtn.addEventListener('click', function () {
                if (generateBtn.disabled) return;
                generateChainOptions(generateBtn, optionsContainer, statusEl);
            });
        }
    }

    function generateChainOptions(btn, optionsEl, statusEl) {
        btn.textContent = 'Finding locations...';
        btn.disabled = true;
        if (statusEl) { statusEl.textContent = ''; statusEl.hidden = true; }

        getLocation()
            .then(function (coords) {
                btn.textContent = 'Building quest chains...';
                return postJSON('quest_chain_generate.php', {
                    latitude: coords.latitude,
                    longitude: coords.longitude
                });
            })
            .then(function (data) {
                if (!data.ok) {
                    if (statusEl) { statusEl.textContent = data.message || 'Could not generate quest chains.'; statusEl.hidden = false; }
                    btn.textContent = 'Discover Quest Chains';
                    btn.disabled = false;
                    return;
                }

                renderChainOptions(data.chains || [], optionsEl, statusEl);
                btn.textContent = 'Refresh Options';
                btn.disabled = false;
            })
            .catch(function (err) {
                if (statusEl) { statusEl.textContent = err.message || 'Failed to generate quest chains.'; statusEl.hidden = false; }
                btn.textContent = 'Discover Quest Chains';
                btn.disabled = false;
            });
    }

    function renderChainOptions(chains, optionsEl, statusEl) {
        if (!optionsEl) return;

        if (chains.length === 0) {
            optionsEl.innerHTML = '';
            if (statusEl) { statusEl.textContent = 'No quest chains available for your area.'; statusEl.hidden = false; }
            return;
        }

        optionsEl.innerHTML = chains.map(function (chain) {
            var stepPills = (chain.steps || []).map(function (step) {
                return '<span class="chain-option-step-pill">' + escapeHtml(step.description) + '</span>';
            }).join('');

            return '<article class="chain-option-card" data-chain-option="' + chain.id + '">' +
                '<div class="chain-option-header">' +
                    '<div>' +
                        '<strong>' + escapeHtml(chain.name) + '</strong>' +
                        '<p>' + escapeHtml(chain.description) + '</p>' +
                    '</div>' +
                    '<span class="chain-xp-badge">+' + chain.xp_reward + ' XP</span>' +
                '</div>' +
                '<div class="chain-option-steps">' + stepPills + '</div>' +
                '<div class="chain-option-footer">' +
                    '<small>' + chain.step_count + ' steps</small>' +
                    '<button type="button" class="chain-btn-primary" data-chain-activate="' + chain.id + '" style="flex:0;padding:8px 16px;">Start Quest</button>' +
                '</div>' +
            '</article>';
        }).join('');

        optionsEl.querySelectorAll('[data-chain-activate]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                activateChain(parseInt(btn.dataset.chainActivate, 10));
            });
        });
    }

    function activateChain(chainId) {
        postJSON('quest_chain_activate.php', { chain_id: chainId })
            .then(function (data) {
                if (!data.ok) {
                    alert(data.message || 'Could not activate quest chain.');
                    return;
                }
                chainsTabLoaded = false;
                loadChainsTab();
            })
            .catch(function () {
                alert('Failed to activate quest chain.');
            });
    }

    function respondToInvite(chainId, accept, btn) {
        var card = btn.closest('[data-chain-invite]');

        postJSON('quest_chain_invite_respond.php', {
            chain_id: chainId,
            accept: accept ? 1 : 0
        })
        .then(function (data) {
            if (!data.ok) {
                alert(data.message || 'Could not respond to invite.');
                return;
            }
            if (accept) {
                chainsTabLoaded = false;
                loadChainsTab();
            } else if (card) {
                card.remove();
            }
        })
        .catch(function () {
            alert('Failed to respond to invite.');
        });
    }

    function openInviteModal(chainId) {
        var existing = document.querySelector('.chain-invite-modal');
        if (existing) existing.remove();

        var modal = document.createElement('div');
        modal.className = 'chain-invite-modal';
        modal.innerHTML =
            '<div class="chain-invite-modal-scrim"></div>' +
            '<div class="chain-invite-modal-body">' +
                '<h3>Invite Friends</h3>' +
                '<p style="color:var(--color-muted);font-size:14px;">Select friends to invite to this quest chain.</p>' +
                '<div data-chain-friend-list><p style="color:var(--color-muted);">Loading friends...</p></div>' +
                '<button type="button" class="chain-btn-secondary" data-chain-invite-close>Close</button>' +
            '</div>';

        document.body.appendChild(modal);

        modal.querySelector('.chain-invite-modal-scrim').addEventListener('click', function () { modal.remove(); });
        modal.querySelector('[data-chain-invite-close]').addEventListener('click', function () { modal.remove(); });

        loadFriendsForInvite(chainId, modal.querySelector('[data-chain-friend-list]'));
    }

    function loadFriendsForInvite(chainId, container) {
        postJSON('quest_chain_status.php', {})
            .then(function (statusData) {
                var memberIds = (statusData.members || []).map(function (m) { return m.user_id; });

                return fetch('friends_list.php', { credentials: 'same-origin' })
                    .then(function (res) { return res.json(); })
                    .then(function (friendsData) {
                        var friends = friendsData.friends || [];

                        if (friends.length === 0) {
                            container.innerHTML = '<p style="color:var(--color-muted);">No friends to invite.</p>';
                            return;
                        }

                        container.innerHTML = friends.map(function (friend) {
                            var isMember = memberIds.indexOf(friend.id) >= 0;
                            return '<div class="chain-invite-friend-item">' +
                                '<span class="chain-invite-friend-name">' + escapeHtml(friend.name) + '</span>' +
                                (isMember
                                    ? '<span style="color:var(--color-muted);font-size:13px;">Already invited</span>'
                                    : '<button type="button" class="chain-btn-primary" data-chain-send-invite="' + friend.id + '" style="flex:0;padding:6px 14px;font-size:13px;">Invite</button>'
                                ) +
                            '</div>';
                        }).join('');

                        container.querySelectorAll('[data-chain-send-invite]').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                var friendId = parseInt(btn.dataset.chainSendInvite, 10);
                                btn.disabled = true;
                                btn.textContent = 'Sending...';

                                postJSON('quest_chain_invite.php', {
                                    chain_id: chainId,
                                    friend_user_id: friendId
                                })
                                .then(function (data) {
                                    if (data.ok) { btn.textContent = 'Invited'; btn.style.opacity = '0.5'; }
                                    else { btn.textContent = 'Failed'; btn.disabled = false; }
                                })
                                .catch(function () { btn.textContent = 'Error'; btn.disabled = false; });
                            });
                        });
                    });
            })
            .catch(function () {
                container.innerHTML = '<p style="color:var(--color-muted);">Could not load friends.</p>';
            });
    }

    function getLocation() {
        return new Promise(function (resolve, reject) {
            if (typeof Capacitor !== 'undefined' && Capacitor.Plugins && Capacitor.Plugins.Geolocation) {
                Capacitor.Plugins.Geolocation.getCurrentPosition({ enableHighAccuracy: true })
                    .then(function (pos) { resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude }); })
                    .catch(function () { fallbackBrowserGeolocation(resolve, reject); });
            } else {
                fallbackBrowserGeolocation(resolve, reject);
            }
        });
    }

    function fallbackBrowserGeolocation(resolve, reject) {
        if (!navigator.geolocation) {
            reject(new Error('Location services are not available.'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            function (pos) { resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude }); },
            function () { reject(new Error('Could not determine your location. Please enable location services.')); },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    function postJSON(endpoint, data) {
        var formData = new FormData();
        formData.append('csrf_token', csrfToken);
        Object.keys(data).forEach(function (key) {
            formData.append(key, data[key]);
        });
        return fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function (res) { return res.json(); });
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};
window.CraftCrawlInitQuestChains();
