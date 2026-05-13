(function () {
    const panel = document.querySelector('[data-business-posts-panel]');

    if (!panel) {
        return;
    }

    const businessId = panel.dataset.businessId;
    const csrfToken = panel.dataset.csrfToken;
    const postsList = panel.querySelector('[data-posts-list]');

    const reactionLabels = {
        cheers: '🍻 Cheers',
        want_to_go: '📍 Want to Go'
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function renderPollResults(options, userVotedOptionId, totalVotes) {
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

    // Delegated reaction handler
    panel.addEventListener('click', function (event) {
        const reactionBtn = event.target.closest('[data-post-reaction]');
        if (!reactionBtn || reactionBtn.disabled) {
            return;
        }

        reactionBtn.disabled = true;

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('item_key', reactionBtn.dataset.itemKey);
        formData.append('reaction_type', reactionBtn.dataset.reactionType);

        fetch('user/feed_reaction_toggle.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                reactionBtn.disabled = false;
                if (!data.ok) {
                    return;
                }

                const reactionsDiv = reactionBtn.closest('.feed-reactions');
                if (!reactionsDiv || !data.reactions) {
                    return;
                }

                const itemKey = reactionBtn.dataset.itemKey;
                const reactionMap = {};
                data.reactions.forEach(function (r) { reactionMap[r.type] = r; });

                reactionsDiv.innerHTML = Object.keys(reactionLabels).map(function (type) {
                    const r = reactionMap[type] || { count: 0, reacted: false };
                    return '<button type="button" class="' + (r.reacted ? 'is-active' : '') + '" '
                        + 'data-post-reaction data-item-key="' + escapeHtml(itemKey) + '" data-reaction-type="' + type + '">'
                        + reactionLabels[type] + (r.count > 0 ? ' ' + r.count : '')
                        + '</button>';
                }).join('');
            })
            .catch(function () {
                reactionBtn.disabled = false;
            });
    });

    // Delegated vote handler
    panel.addEventListener('click', function (event) {
        const voteBtn = event.target.closest('[data-vote-option]');
        if (!voteBtn || voteBtn.disabled) {
            return;
        }

        const optionsContainer = voteBtn.closest('[data-poll-options]');
        if (!optionsContainer) {
            return;
        }

        const postId = optionsContainer.dataset.postId;
        const optionId = voteBtn.dataset.optionId;

        optionsContainer.querySelectorAll('[data-vote-option]').forEach(function (btn) {
            btn.disabled = true;
        });

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('post_id', postId);
        formData.append('option_id', optionId);

        fetch('user/business_poll_vote.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    optionsContainer.querySelectorAll('[data-vote-option]').forEach(function (btn) {
                        btn.disabled = false;
                    });
                    return;
                }

                const pollCard = optionsContainer.closest('[data-post-id]');
                if (!pollCard) {
                    return;
                }

                const resultsHtml = renderPollResults(data.options, data.user_voted_option_id, data.total_votes);
                const div = document.createElement('div');
                div.innerHTML = resultsHtml;

                const oldTotal = pollCard.querySelector('.business-poll-total');
                if (oldTotal) {
                    oldTotal.remove();
                }

                optionsContainer.replaceWith(div.firstElementChild);
            })
            .catch(function () {
                optionsContainer.querySelectorAll('[data-vote-option]').forEach(function (btn) {
                    btn.disabled = false;
                });
            });
    });

    // Delegated load-more handler
    panel.addEventListener('click', function (event) {
        const loadMoreBtn = event.target.closest('[data-load-more-posts]');
        if (!loadMoreBtn || loadMoreBtn.disabled) {
            return;
        }

        const before = loadMoreBtn.dataset.lastDate;
        loadMoreBtn.disabled = true;
        loadMoreBtn.textContent = 'Loading…';

        fetch('business_posts.php?business_id=' + encodeURIComponent(businessId) + '&before=' + encodeURIComponent(before), {
            credentials: 'same-origin'
        })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                const div = document.createElement('div');
                div.innerHTML = html;

                const sentinel = div.querySelector('[data-load-more-sentinel]');
                const hasMore = sentinel !== null;
                const lastDate = sentinel ? sentinel.dataset.lastDate : null;
                if (sentinel) {
                    sentinel.remove();
                }

                const target = postsList || loadMoreBtn.parentNode;
                while (div.firstChild) {
                    target.insertBefore(div.firstChild, postsList ? null : loadMoreBtn);
                }

                if (hasMore && lastDate) {
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.textContent = 'Load more posts';
                    loadMoreBtn.dataset.lastDate = lastDate;
                } else {
                    loadMoreBtn.remove();
                }
            })
            .catch(function () {
                loadMoreBtn.disabled = false;
                loadMoreBtn.textContent = 'Load more posts';
            });
    });
}());
