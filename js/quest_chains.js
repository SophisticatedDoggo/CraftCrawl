(function () {
    'use strict';

    var chainsLoading = false;

    function getCsrfToken() {
        var panel = document.querySelector('[data-quest-chains-panel]');
        return (panel && panel.dataset.csrfToken) || window.CRAFTCRAWL_CSRF_TOKEN || '';
    }

    function chainsNeedLoad(panel) {
        var content = panel && panel.querySelector('[data-chain-content]');
        return content && !content.dataset.chainLoaded;
    }

    document.addEventListener('click', function (e) {
        var tab = e.target.closest('[data-quest-subtab]');
        if (!tab) return;

        var panel = tab.closest('[data-quest-chains-panel]');
        if (!panel) return;

        var target = tab.dataset.questSubtab;

        panel.querySelectorAll('[data-quest-subtab]').forEach(function (t) {
            t.classList.toggle('is-active', t === tab);
            t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
        });

        panel.querySelectorAll('[data-quest-subtab-panel]').forEach(function (p) {
            p.hidden = p.dataset.questSubtabPanel !== target;
        });

        if (target === 'chains' && !chainsLoading) {
            markChainInvitesSeen();
            loadChainsTab(panel);
        }
    });

    document.addEventListener('click', function (e) {
        var btn;

        btn = e.target.closest('[data-chain-generate]');
        if (btn && !btn.disabled) {
            generateChainOptions(btn);
            return;
        }

        btn = e.target.closest('[data-chain-activate]');
        if (btn) {
            e.stopPropagation();
            activateChain(parseInt(btn.dataset.chainActivate, 10));
            return;
        }

        btn = e.target.closest('[data-chain-abandon]');
        if (btn) {
            if (!confirm('Abandon this quest chain? All progress will be lost.')) return;
            var chainId = parseInt(btn.dataset.chainAbandon, 10);
            postJSON('quest_chain_abandon.php', { chain_id: chainId })
                .then(function (data) {
                    if (!data.ok) { alert(data.message || 'Could not abandon quest chain.'); return; }
                    reloadChainsTab();
                })
                .catch(function () { alert('Failed to abandon quest chain.'); });
            return;
        }

        btn = e.target.closest('[data-chain-invite-accept]');
        if (btn) {
            respondToInvite(parseInt(btn.dataset.chainInviteAccept, 10), true, btn);
            return;
        }

        btn = e.target.closest('[data-chain-invite-decline]');
        if (btn) {
            respondToInvite(parseInt(btn.dataset.chainInviteDecline, 10), false, btn);
            return;
        }

        btn = e.target.closest('[data-chain-promote]');
        if (btn && !btn.disabled) {
            promoteToLeader(btn);
            return;
        }

        btn = e.target.closest('[data-chain-invite-friends]');
        if (btn) {
            openInviteModal(parseInt(btn.dataset.chainInviteFriends, 10));
            return;
        }

        btn = e.target.closest('[data-chain-send-invite]');
        if (btn && !btn.disabled) {
            sendChainInvite(btn);
            return;
        }

        btn = e.target.closest('[data-chain-cancel-invite]');
        if (btn && !btn.disabled) {
            cancelChainInvite(btn);
            return;
        }

        if (e.target.closest('.chain-invite-modal-scrim') || e.target.closest('[data-chain-invite-close]')) {
            var modal = document.querySelector('.chain-invite-modal');
            if (modal) modal.remove();
            return;
        }
    });

    function loadChainsTab(panel) {
        var container = panel && panel.querySelector('[data-chain-content]');
        if (!container) return;

        chainsLoading = true;
        container.innerHTML = '<p class="chain-status-message">Loading quest chains...</p>';

        postJSON('quest_chain_status.php', {})
            .then(function (data) {
                chainsLoading = false;
                if (!data.ok) {
                    container.innerHTML = '<p class="chain-status-message">Could not load quest chains.</p>';
                    return;
                }
                renderChainsTab(container, data);
                container.dataset.chainLoaded = 'true';
                clearChainBadges();
            })
            .catch(function () {
                chainsLoading = false;
                container.innerHTML = '<p class="chain-status-message">Could not load quest chains.</p>';
            });
    }

    function reloadChainsTab() {
        var panel = document.querySelector('[data-quest-chains-panel]');
        var container = panel && panel.querySelector('[data-chain-content]');
        if (container) delete container.dataset.chainLoaded;
        if (panel) loadChainsTab(panel);
    }

    function renderChainsTab(container, data) {
        var html = '';

        if (data.pending_invites && data.pending_invites.length > 0) {
            html += '<div class="chain-invites">';
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
            html += renderActiveChain(data.active_chain, data.members || []);
        } else {
            html += '<div class="chain-discover">' +
                '<div class="chain-discover-header">' +
                    '<h3 class="chain-section-heading">Quest Chains</h3>' +
                    '<p>Multi-step adventures through locations near you. Check in, leave reviews, and explore new spots to earn bonus XP.</p>' +
                '</div>' +
                '<div class="chain-options" data-chain-options></div>' +
                '<div class="chain-discover-actions">' +
                    '<button type="button" data-chain-generate class="chain-btn-primary">Discover Quest Chains</button>' +
                '</div>' +
                '<p class="chain-status-message" data-chain-status hidden></p>' +
            '</div>';
        }

        container.innerHTML = html;
    }

    function renderActiveChain(chain, members) {
        var actionLabels = { checkin: 'Check in', review: 'Leave a review', event_want_to_go: 'RSVP to an event', feed_reaction: 'React to a post' };
        var html = '<div class="chain-active">';
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
            var cls = step.completed ? ' is-complete' : '';
            var label = actionLabels[step.action_type] || step.action_type;
            var loc = escapeHtml(step.location_name);
            if (step.location_city) loc += ' · ' + escapeHtml(step.location_city);

            html += '<li class="chain-step' + cls + '">';
            html += '<span class="chain-step-dot" aria-hidden="true"></span>';
            html += '<div class="chain-step-content"><strong>' + escapeHtml(label) + '</strong><span>' + loc + '</span></div>';
            if (step.completed) {
                html += '<span class="chain-step-check" aria-label="Completed"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8.5L6.5 12L13 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
            }
            html += '</li>';
        });

        html += '</ol>';

        if (members.length > 1) {
            html += '<div class="chain-members-section">';
            html += '<h4 class="chain-members-heading">Party Progress</h4>';
            members.forEach(function (m) {
                var pct = chain.step_count > 0 ? Math.round((m.completed_count / chain.step_count) * 100) : 0;
                html += '<div class="chain-member-row">';
                html += renderAvatar(m.actor, m.name, 'chain-member-avatar');
                html += '<div class="chain-member-info">';
                html += '<span class="chain-member-name">' + escapeHtml(m.name) + (m.role === 'owner' ? ' <small>(party leader)</small>' : '') + '</span>';
                html += '<div class="chain-member-bar"><span style="width:' + pct + '%;"></span></div>';
                html += '<small>' + m.completed_count + ' / ' + chain.step_count + ' steps</small>';
                html += '</div>';
                if (chain.is_owner && m.role !== 'owner') {
                    html += '<button type="button" class="chain-btn-promote" data-chain-promote="' + m.user_id + '" data-chain-id="' + chain.id + '" title="Make party leader"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></button>';
                } else if (chain.is_owner && m.role === 'owner') {
                    html += '<span class="chain-member-row-leader-spacer"></span>';
                }
                html += '</div>';
            });
            html += '</div>';
        }

        html += '<div class="chain-actions">';
        if (chain.is_owner) {
            html += '<button type="button" data-chain-invite-friends="' + chain.id + '" class="chain-btn-primary">Invite Friends</button>';
        }
        html += '<button type="button" data-chain-abandon="' + chain.id + '" class="chain-btn-danger">' + (chain.is_owner ? 'Abandon Quest' : 'Leave Quest') + '</button>';
        html += '</div></article></div>';
        return html;
    }

    function generateChainOptions(btn) {
        var container = btn.closest('.chain-discover');
        var optionsEl = container && container.querySelector('[data-chain-options]');
        var statusEl = container && container.querySelector('[data-chain-status]');

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
                btn.textContent = 'Refresh Quests';
                btn.className = 'chain-btn-secondary';
                btn.disabled = false;
            })
            .catch(function (err) {
                if (statusEl) { statusEl.textContent = err.message || 'Failed to generate quest chains.'; statusEl.hidden = false; }
                btn.textContent = 'Discover Quest Chains';
                btn.className = 'chain-btn-primary';
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

        var actionLabels = { checkin: 'Check in at', review: 'Review', event_want_to_go: 'RSVP at', feed_reaction: 'React to a post about' };

        optionsEl.innerHTML = chains.map(function (chain) {
            var stepsList = (chain.steps || []).map(function (step, i) {
                var label = actionLabels[step.action_type] || 'Visit';
                return '<li class="chain-option-step">' +
                    '<span class="chain-option-step-num">' + (i + 1) + '</span>' +
                    '<span>' + escapeHtml(label) + ' <strong>' + escapeHtml(step.location_name) + '</strong></span>' +
                '</li>';
            }).join('');

            return '<article class="chain-option-card">' +
                '<div class="chain-option-header"><div>' +
                    '<strong>' + escapeHtml(chain.name) + '</strong>' +
                    '<p>' + escapeHtml(chain.description) + '</p>' +
                '</div>' +
                '<span class="chain-xp-badge">+' + chain.xp_reward + ' XP</span></div>' +
                '<ol class="chain-option-step-list">' + stepsList + '</ol>' +
                '<div class="chain-option-footer">' +
                    '<button type="button" class="chain-btn-start" data-chain-activate="' + chain.id + '">Start Quest</button>' +
                '</div>' +
            '</article>';
        }).join('');
    }

    function activateChain(chainId) {
        postJSON('quest_chain_activate.php', { chain_id: chainId })
            .then(function (data) {
                if (!data.ok) { alert(data.message || 'Could not activate quest chain.'); return; }
                reloadChainsTab();
            })
            .catch(function () { alert('Failed to activate quest chain.'); });
    }

    function respondToInvite(chainId, accept, btn) {
        var card = btn.closest('[data-chain-invite]');
        postJSON('quest_chain_invite_respond.php', { chain_id: chainId, accept: accept ? 1 : 0 })
            .then(function (data) {
                if (!data.ok) { alert(data.message || 'Could not respond to invite.'); return; }
                if (accept) {
                    reloadChainsTab();
                } else if (card) {
                    card.remove();
                }
            })
            .catch(function () { alert('Failed to respond to invite.'); });
    }

    function promoteToLeader(btn) {
        var newOwnerId = parseInt(btn.dataset.chainPromote, 10);
        var chainId = parseInt(btn.dataset.chainId, 10);
        var memberName = btn.closest('.chain-member-row').querySelector('.chain-member-name');
        var name = memberName ? memberName.textContent.trim() : 'this member';

        if (!confirm('Make ' + name + ' the party leader? You will become a regular member.')) return;

        btn.disabled = true;
        postJSON('quest_chain_transfer.php', { chain_id: chainId, new_owner_id: newOwnerId })
            .then(function (data) {
                if (!data.ok) { alert(data.message || 'Could not transfer leadership.'); btn.disabled = false; return; }
                reloadChainsTab();
            })
            .catch(function () { alert('Failed to transfer leadership.'); btn.disabled = false; });
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
                '<p class="chain-invite-subtitle" data-chain-invite-subtitle style="color:var(--color-muted);font-size:14px;">Loading...</p>' +
                '<div data-chain-friend-list><p style="color:var(--color-muted);">Loading friends...</p></div>' +
                '<button type="button" class="chain-btn-secondary" data-chain-invite-close>Close</button>' +
            '</div>';
        document.body.appendChild(modal);
        loadFriendsForInvite(chainId, modal.querySelector('[data-chain-friend-list]'));
    }

    function sendChainInvite(btn) {
        var chainId = parseInt(btn.closest('.chain-invite-modal-body').querySelector('[data-chain-invite-close]') ? btn.dataset.chainSendInvite : '0', 10);
        var friendId = parseInt(btn.dataset.chainSendInvite, 10);
        btn.disabled = true;
        btn.textContent = 'Sending...';

        var activeFriendsBtn = document.querySelector('[data-chain-invite-friends]');
        var activeChainId = activeFriendsBtn ? parseInt(activeFriendsBtn.dataset.chainInviteFriends, 10) : 0;

        postJSON('quest_chain_invite.php', { chain_id: activeChainId, friend_user_id: friendId })
            .then(function (data) {
                if (data.ok) {
                    var cancelBtn = document.createElement('button');
                    cancelBtn.type = 'button';
                    cancelBtn.className = 'chain-btn-cancel-invite';
                    cancelBtn.dataset.chainCancelInvite = friendId;
                    cancelBtn.textContent = 'Cancel';
                    btn.replaceWith(cancelBtn);
                    updateInviteSlotCount(1);
                } else {
                    btn.textContent = data.message || 'Failed';
                    btn.disabled = false;
                }
            })
            .catch(function () { btn.textContent = 'Error'; btn.disabled = false; });
    }

    function cancelChainInvite(btn) {
        var friendId = parseInt(btn.dataset.chainCancelInvite, 10);
        btn.disabled = true;
        btn.textContent = 'Canceling...';

        var activeFriendsBtn = document.querySelector('[data-chain-invite-friends]');
        var activeChainId = activeFriendsBtn ? parseInt(activeFriendsBtn.dataset.chainInviteFriends, 10) : 0;

        postJSON('quest_chain_invite_cancel.php', { chain_id: activeChainId, friend_user_id: friendId })
            .then(function (data) {
                if (data.ok) {
                    var newBtn = document.createElement('button');
                    newBtn.type = 'button';
                    newBtn.className = 'chain-btn-invite';
                    newBtn.dataset.chainSendInvite = friendId;
                    newBtn.textContent = 'Invite';
                    btn.replaceWith(newBtn);
                    updateInviteSlotCount(-1);
                } else {
                    btn.textContent = 'Cancel';
                    btn.disabled = false;
                }
            })
            .catch(function () { btn.textContent = 'Cancel'; btn.disabled = false; });
    }

    function updateInviteSlotCount(delta) {
        var modal = document.querySelector('.chain-invite-modal-body');
        if (!modal) return;

        var count = parseInt(modal.dataset.chainInviteCount || '0', 10) + delta;
        if (count < 0) count = 0;
        modal.dataset.chainInviteCount = count;

        var subtitle = modal.querySelector('[data-chain-invite-subtitle]');
        var atLimit = count >= 4;
        if (subtitle) {
            subtitle.textContent = count + ' / 4 friends invited' + (atLimit ? ' — party full (5 max)' : '');
        }

        modal.querySelectorAll('.chain-invite-friend-item').forEach(function (item) {
            var hasAction = item.querySelector('[data-chain-send-invite], [data-chain-cancel-invite], .chain-invite-status');
            if (hasAction) {
                var inviteBtn = item.querySelector('[data-chain-send-invite]');
                if (inviteBtn) {
                    inviteBtn.disabled = atLimit;
                    inviteBtn.style.opacity = atLimit ? '0.4' : '';
                }
                return;
            }

            if (!atLimit) {
                var friendInfo = item.querySelector('.chain-invite-friend-info');
                var friendId = item.dataset.chainFriendId;
                if (friendId) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'chain-btn-invite';
                    btn.dataset.chainSendInvite = friendId;
                    btn.textContent = 'Invite';
                    item.appendChild(btn);
                }
            }
        });
    }

    function loadFriendsForInvite(chainId, container) {
        postJSON('quest_chain_status.php', {})
            .then(function (statusData) {
                var memberIds = (statusData.members || []).map(function (m) { return m.user_id; });
                var pendingIds = (statusData.sent_invites || []).map(function (s) { return s.user_id; });
                var totalInvited = memberIds.length - 1 + pendingIds.length;
                var atLimit = totalInvited >= 4;

                var modal = container.closest('.chain-invite-modal-body');
                if (modal) modal.dataset.chainInviteCount = totalInvited;

                var subtitle = document.querySelector('[data-chain-invite-subtitle]');
                if (subtitle) {
                    subtitle.textContent = totalInvited + ' / 4 friends invited' + (atLimit ? ' — party full (5 max)' : '');
                }

                return fetch('friends_list.php', { credentials: 'same-origin' })
                    .then(function (res) { return res.json(); })
                    .then(function (friendsData) {
                        var friends = friendsData.friends || [];
                        if (friends.length === 0) {
                            container.innerHTML = '<p style="color:var(--color-muted);">No friends to invite.</p>';
                            return;
                        }

                        var html = '';

                        html += friends.map(function (friend) {
                            var isMember = memberIds.indexOf(friend.id) >= 0;
                            var isPending = pendingIds.indexOf(friend.id) >= 0;
                            var avatar = renderAvatar(friend.actor, friend.name, 'chain-invite-avatar');

                            var username = friend.username
                                ? '<span class="chain-invite-username">@' + escapeHtml(friend.username) + '</span>'
                                : '';

                            var levelInfo = '<span class="chain-invite-level">Lv. ' + friend.level + (friend.title ? ' · ' + escapeHtml(friend.title) : '') + '</span>';

                            var action;
                            if (isMember) {
                                action = '<span class="chain-invite-status">Joined</span>';
                            } else if (isPending) {
                                action = '<button type="button" class="chain-btn-cancel-invite" data-chain-cancel-invite="' + friend.id + '">Cancel</button>';
                            } else if (atLimit) {
                                action = '';
                            } else {
                                action = '<button type="button" class="chain-btn-invite" data-chain-send-invite="' + friend.id + '">Invite</button>';
                            }

                            return '<div class="chain-invite-friend-item" data-chain-friend-id="' + friend.id + '">' +
                                avatar +
                                '<div class="chain-invite-friend-info">' +
                                    '<span class="chain-invite-friend-name">' + escapeHtml(friend.name) + '</span>' +
                                    username +
                                    levelInfo +
                                '</div>' +
                                action +
                            '</div>';
                        }).join('');

                        container.innerHTML = html;
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
                    .catch(function () { fallbackGeo(resolve, reject); });
            } else {
                fallbackGeo(resolve, reject);
            }
        });
    }

    function fallbackGeo(resolve, reject) {
        if (!navigator.geolocation) { reject(new Error('Location services are not available.')); return; }
        navigator.geolocation.getCurrentPosition(
            function (pos) { resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude }); },
            function () { reject(new Error('Could not determine your location. Please enable location services.')); },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    function postJSON(endpoint, data) {
        var formData = new FormData();
        formData.append('csrf_token', getCsrfToken());
        Object.keys(data).forEach(function (key) { formData.append(key, data[key]); });
        return fetch(endpoint, { method: 'POST', credentials: 'same-origin', body: formData })
            .then(function (res) { return res.json(); });
    }

    function markChainInvitesSeen() {
        var token = getCsrfToken();
        if (!token) return;
        var formData = new FormData();
        formData.append('csrf_token', token);
        fetch('quest_chain_invites_seen.php', { method: 'POST', credentials: 'same-origin', body: formData }).catch(function () {});
        window._chainInvitesSuppressed = true;
        clearChainBadges();
    }

    function clearChainBadges() {
        document.querySelectorAll('[data-quests-tab-badge]').forEach(function (badge) {
            badge.textContent = '';
            badge.hidden = true;
        });
        document.querySelectorAll('.quest-subtab-badge').forEach(function (badge) {
            badge.remove();
        });
    }

    window.addEventListener('craftcrawl:user-tab-changed', function (e) {
        if (e.detail && e.detail.tab === 'quests') {
            var panel = document.querySelector('[data-quest-chains-panel]');
            var chainsSubtab = panel && panel.querySelector('[data-quest-subtab="chains"].is-active');
            if (chainsSubtab) {
                if (chainsNeedLoad(panel) && !chainsLoading) {
                    loadChainsTab(panel);
                }
                markChainInvitesSeen();
            }
        }
    });

    window.addEventListener('craftcrawl:notification-counts-changed', function (e) {
        var counts = e.detail;
        if (!counts || window._chainInvitesSuppressed) return;
        var panel = document.querySelector('[data-quest-chains-panel]');
        var chainsBtn = panel && panel.querySelector('[data-quest-subtab="chains"]');
        if (!chainsBtn) return;
        var existing = chainsBtn.querySelector('.quest-subtab-badge');
        if (counts.pendingChainInvites > 0) {
            if (!existing) {
                existing = document.createElement('span');
                existing.className = 'quest-subtab-badge';
                chainsBtn.appendChild(existing);
            }
            existing.textContent = counts.pendingChainInvites > 9 ? '9+' : String(counts.pendingChainInvites);
        } else if (existing) {
            existing.remove();
        }
    });

    function autoSelectChainsTab() {
        var params = new URLSearchParams(window.location.search);
        if (params.get('tab') !== 'chains') return;

        var panel = document.querySelector('[data-quest-chains-panel]');
        if (!panel) return;

        var chainsBtn = panel.querySelector('[data-quest-subtab="chains"]');
        if (chainsBtn) chainsBtn.click();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoSelectChainsTab);
    } else {
        setTimeout(autoSelectChainsTab, 0);
    }

    window.addEventListener('craftcrawl:user-tab-changed', function (e) {
        if (e.detail && e.detail.tab === 'quests' && e.detail.url && e.detail.url.indexOf('tab=chains') !== -1) {
            setTimeout(autoSelectChainsTab, 50);
        }
    });

    function renderAvatar(actor, fallbackName, cssClass) {
        var data = actor || {};
        var frame = String(data.frame || '').replace(/[^a-z0-9_-]/gi, '');
        var frameStyle = String(data.frame_style || 'solid').replace(/[^a-z0-9_-]/gi, '') || 'solid';
        var classes = 'user-avatar user-avatar-medium ' + (cssClass || '') + (frame ? ' has-frame-' + frame + ' has-frame-style-' + frameStyle : '');
        var name = data.name || fallbackName || '?';
        var initials = data.initials || name.charAt(0).toUpperCase();

        if (data.avatar_url) {
            return '<span class="' + escapeHtml(classes) + '"><img src="' + escapeHtml(data.avatar_url) + '" alt=""></span>';
        }
        return '<span class="' + escapeHtml(classes) + '"><span>' + escapeHtml(initials) + '</span></span>';
    }

    function escapeHtml(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
})();
