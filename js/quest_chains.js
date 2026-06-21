(function () {
    'use strict';

    const panel = document.querySelector('[data-quest-chains-panel]');
    if (!panel) return;

    const csrfToken = panel.dataset.csrfToken || window.CRAFTCRAWL_CSRF_TOKEN || '';

    const subtabs = panel.querySelectorAll('[data-quest-subtab]');
    const subtabPanels = panel.querySelectorAll('[data-quest-subtab-panel]');

    subtabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const target = tab.dataset.questSubtab;

            subtabs.forEach(function (t) {
                t.classList.toggle('is-active', t === tab);
                t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
            });

            subtabPanels.forEach(function (p) {
                p.hidden = p.dataset.questSubtabPanel !== target;
            });
        });
    });

    const generateBtn = panel.querySelector('[data-chain-generate]');
    const optionsContainer = panel.querySelector('[data-chain-options]');
    const statusMessage = panel.querySelector('[data-chain-status]');
    const discoverActions = panel.querySelector('.chain-discover-actions');
    let isGenerating = false;

    if (generateBtn) {
        generateBtn.addEventListener('click', function () {
            if (isGenerating) return;
            generateChainOptions();
        });
    }

    function generateChainOptions() {
        isGenerating = true;
        if (generateBtn) generateBtn.textContent = 'Finding locations...';
        if (generateBtn) generateBtn.disabled = true;
        showStatus('');

        getLocation()
            .then(function (coords) {
                if (generateBtn) generateBtn.textContent = 'Building quest chains...';
                return postJSON('quest_chain_generate.php', {
                    latitude: coords.latitude,
                    longitude: coords.longitude
                });
            })
            .then(function (data) {
                if (!data.ok) {
                    showStatus(data.message || 'Could not generate quest chains.');
                    resetGenerateButton();
                    return;
                }

                renderChainOptions(data.chains || []);
                resetGenerateButton('Refresh Options');
            })
            .catch(function (err) {
                showStatus(err.message || 'Failed to generate quest chains.');
                resetGenerateButton();
            });
    }

    function resetGenerateButton(label) {
        isGenerating = false;
        if (generateBtn) {
            generateBtn.textContent = label || 'Discover Quest Chains';
            generateBtn.disabled = false;
        }
    }

    function renderChainOptions(chains) {
        if (!optionsContainer) return;

        if (chains.length === 0) {
            optionsContainer.innerHTML = '';
            showStatus('No quest chains available for your area.');
            return;
        }

        optionsContainer.innerHTML = chains.map(function (chain) {
            const stepPills = (chain.steps || []).map(function (step) {
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

        optionsContainer.querySelectorAll('[data-chain-activate]').forEach(function (btn) {
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
                    showStatus(data.message || 'Could not activate quest chain.');
                    return;
                }

                window.location.reload();
            })
            .catch(function (err) {
                showStatus(err.message || 'Failed to activate quest chain.');
            });
    }

    var abandonBtns = panel.querySelectorAll('[data-chain-abandon]');
    abandonBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Abandon this quest chain? All progress will be lost.')) return;

            var chainId = parseInt(btn.dataset.chainAbandon, 10);
            postJSON('quest_chain_abandon.php', { chain_id: chainId })
                .then(function (data) {
                    if (!data.ok) {
                        alert(data.message || 'Could not abandon quest chain.');
                        return;
                    }

                    window.location.reload();
                })
                .catch(function () {
                    alert('Failed to abandon quest chain.');
                });
        });
    });

    var inviteAcceptBtns = panel.querySelectorAll('[data-chain-invite-accept]');
    inviteAcceptBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var chainId = parseInt(btn.dataset.chainInviteAccept, 10);
            respondToInvite(chainId, true, btn);
        });
    });

    var inviteDeclineBtns = panel.querySelectorAll('[data-chain-invite-decline]');
    inviteDeclineBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var chainId = parseInt(btn.dataset.chainInviteDecline, 10);
            respondToInvite(chainId, false, btn);
        });
    });

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
                window.location.reload();
            } else if (card) {
                card.remove();
            }
        })
        .catch(function () {
            alert('Failed to respond to invite.');
        });
    }

    var inviteFriendBtns = panel.querySelectorAll('[data-chain-invite-friends]');
    inviteFriendBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var chainId = parseInt(btn.dataset.chainInviteFriends, 10);
            openInviteModal(chainId);
        });
    });

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

        modal.querySelector('.chain-invite-modal-scrim').addEventListener('click', function () {
            modal.remove();
        });

        modal.querySelector('[data-chain-invite-close]').addEventListener('click', function () {
            modal.remove();
        });

        loadFriendsForInvite(chainId, modal.querySelector('[data-chain-friend-list]'));
    }

    function loadFriendsForInvite(chainId, container) {
        postJSON('quest_chain_status.php', {})
            .then(function (statusData) {
                var memberIds = (statusData.members || []).map(function (m) { return m.user_id; });

                return fetch('../user/friends_list.php', {
                    credentials: 'same-origin'
                })
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
                                if (data.ok) {
                                    btn.textContent = 'Invited';
                                    btn.style.opacity = '0.5';
                                } else {
                                    btn.textContent = 'Failed';
                                    btn.disabled = false;
                                }
                            })
                            .catch(function () {
                                btn.textContent = 'Error';
                                btn.disabled = false;
                            });
                        });
                    });
                });
            })
            .catch(function () {
                container.innerHTML = '<p style="color:var(--color-muted);">Could not load friends.</p>';
            });
    }

    function showStatus(msg) {
        if (!statusMessage) return;
        statusMessage.textContent = msg;
        statusMessage.hidden = !msg;
    }

    function getLocation() {
        return new Promise(function (resolve, reject) {
            if (typeof Capacitor !== 'undefined' && Capacitor.Plugins && Capacitor.Plugins.Geolocation) {
                Capacitor.Plugins.Geolocation.getCurrentPosition({ enableHighAccuracy: true })
                    .then(function (pos) {
                        resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude });
                    })
                    .catch(function () {
                        fallbackBrowserGeolocation(resolve, reject);
                    });
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
            function (pos) {
                resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude });
            },
            function () {
                reject(new Error('Could not determine your location. Please enable location services.'));
            },
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
        }).then(function (res) {
            return res.json();
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
})();
