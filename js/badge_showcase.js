(function () {
    const section = document.querySelector('[data-badge-showcase]');

    if (!section) {
        return;
    }

    const csrfToken = section.dataset.csrfToken || '';
    const slotCount = parseInt(section.dataset.slotCount || '1', 10);

    function postForm(url, values) {
        const formData = new FormData();
        Object.keys(values).forEach((key) => formData.append(key, values[key]));
        return fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then((r) => r.json());
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function renderShowcase(showcase) {
        const grid = section.querySelector('[data-showcase-grid]');
        if (!grid) return;

        const bySlot = {};
        showcase.forEach((s) => { bySlot[s.slot_order] = s; });

        let html = '';
        for (let s = 1; s <= slotCount; s++) {
            const badge = bySlot[s];
            if (badge) {
                html += `
                    <article class="badge-showcase-slot is-filled" data-showcase-slot="${s}">
                        <strong>${escapeHtml(badge.badge_name)}</strong>
                        <small>${escapeHtml(badge.badge_tier)}</small>
                        <button type="button" class="badge-showcase-remove" data-showcase-action="remove" data-badge-key="${escapeHtml(badge.badge_key)}">Remove</button>
                    </article>
                `;
            } else {
                html += `
                    <article class="badge-showcase-slot" data-showcase-slot="${s}">
                        <span class="badge-showcase-empty">Empty slot</span>
                    </article>
                `;
            }
        }
        grid.innerHTML = html;
        wireShowcaseButtons(grid);
    }

    function updateFeatureButtons(showcase) {
        const showcasedKeys = new Set(showcase.map((s) => s.badge_key));
        const isFull = showcase.length >= slotCount;

        document.querySelectorAll('.badge-card').forEach((card) => {
            const addBtn = card.querySelector('[data-showcase-action="add"]');
            const removeBtn = card.querySelector('[data-showcase-action="remove"]');
            const badgeKey = addBtn?.dataset.badgeKey || removeBtn?.dataset.badgeKey;

            if (!badgeKey) return;

            const isShowcased = showcasedKeys.has(badgeKey);
            card.classList.toggle('is-showcased', isShowcased);

            if (addBtn) {
                addBtn.hidden = isShowcased || isFull;
            }
            if (removeBtn) {
                removeBtn.hidden = !isShowcased;
            }
        });
    }

    function handleShowcaseAction(button) {
        const action = button.dataset.showcaseAction;
        const badgeKey = button.dataset.badgeKey;

        if (!action || !badgeKey) return;

        button.disabled = true;
        button.classList.add('is-loading');

        postForm('update_badge_showcase.php', {
            csrf_token: csrfToken,
            action,
            badge_key: badgeKey
        })
            .then((data) => {
                if (!data.ok) {
                    alert(data.message || 'Could not update badge showcase.');
                    return;
                }
                renderShowcase(data.showcase);
                updateFeatureButtons(data.showcase);
            })
            .catch(() => alert('Could not update badge showcase. Please try again.'))
            .finally(() => {
                button.disabled = false;
                button.classList.remove('is-loading');
            });
    }

    function wireShowcaseButtons(root) {
        root.querySelectorAll('[data-showcase-action]').forEach((btn) => {
            btn.addEventListener('click', () => handleShowcaseAction(btn));
        });
    }

    wireShowcaseButtons(section);

    document.querySelectorAll('[data-showcase-action]').forEach((btn) => {
        if (!section.contains(btn)) {
            btn.addEventListener('click', () => handleShowcaseAction(btn));
        }
    });
}());
