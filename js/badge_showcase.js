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

        document.querySelectorAll('[data-earned-badge-card]').forEach((card) => {
            const badgeKey = card.dataset.badgeKey;

            if (!badgeKey) return;

            const isShowcased = showcasedKeys.has(badgeKey);
            const canFeature = !isShowcased && !isFull;

            card.classList.toggle('is-showcased', isShowcased);
            card.classList.toggle('can-feature', canFeature);
            card.setAttribute('aria-disabled', String(!canFeature));
            card.tabIndex = canFeature ? 0 : -1;

            if (canFeature) {
                const badgeName = card.querySelector('strong')?.textContent || 'badge';
                card.setAttribute('role', 'button');
                card.setAttribute('aria-label', `Feature ${badgeName}`);
            } else {
                card.removeAttribute('role');
                card.removeAttribute('aria-label');
            }
        });
    }

    function updateBadgeShowcase(target, action, badgeKey) {
        if (!action || !badgeKey) return;

        if (target instanceof HTMLButtonElement) {
            target.disabled = true;
        }
        target.classList.add('is-loading');

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
                if (target instanceof HTMLButtonElement) {
                    target.disabled = false;
                }
                target.classList.remove('is-loading');
            });
    }

    function handleShowcaseAction(button) {
        updateBadgeShowcase(button, button.dataset.showcaseAction, button.dataset.badgeKey);
    }

    function handleEarnedBadgeFeature(card) {
        if (!card.classList.contains('can-feature') || card.classList.contains('is-loading')) {
            return;
        }

        updateBadgeShowcase(card, 'add', card.dataset.badgeKey);
    }

    function wireShowcaseButtons(root) {
        root.querySelectorAll('[data-showcase-action]').forEach((btn) => {
            btn.addEventListener('click', () => handleShowcaseAction(btn));
        });
    }

    wireShowcaseButtons(section);

    document.querySelectorAll('[data-earned-badge-card]').forEach((card) => {
        card.addEventListener('click', () => handleEarnedBadgeFeature(card));
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                handleEarnedBadgeFeature(card);
            }
        });
    });

    updateFeatureButtons(Array.from(section.querySelectorAll('.badge-showcase-slot.is-filled')).map((slot) => {
        const button = slot.querySelector('[data-showcase-action="remove"]');
        return {
            slot_order: Number(slot.dataset.showcaseSlot || 0),
            badge_key: button?.dataset.badgeKey || ''
        };
    }).filter((badge) => badge.badge_key));
}());
