window.CraftCrawlInitBadgeShowcase = function (root = document) {
    const section = root.querySelector('[data-badge-showcase]');

    if (!section || section.dataset.shellReady === 'true') {
        return;
    }
    section.dataset.shellReady = 'true';

    const csrfToken = section.dataset.csrfToken || '';
    const slotCount = parseInt(section.dataset.slotCount || '1', 10);
    const editor = section.querySelector('[data-showcase-editor]');
    const status = section.querySelector('[data-showcase-editor-status]');
    const slotsWrap = section.querySelector('[data-showcase-editor-slots]');
    const earnedList = section.querySelector('[data-showcase-earned-list]');
    const earnedSearch = section.querySelector('[data-earned-badge-search]');
    const earnedEmpty = section.querySelector('[data-earned-badge-empty]');
    let statusTimer = null;
    let lockedScrollY = 0;

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

    function badgeIconPath(badgeKey) {
        return `../images/badges/${encodeURIComponent(String(badgeKey || ''))}.svg`;
    }

    function badgeData(badgeKey) {
        const badge = Array.from(section.querySelectorAll('[data-earned-badge]'))
            .find((item) => (item.dataset.badgeKey || '') === badgeKey);
        if (!badge) return null;

        return {
            badge_key: badge.dataset.badgeKey || '',
            badge_name: badge.dataset.badgeName || '',
            badge_description: badge.dataset.badgeDescription || '',
            badge_requirement: badge.dataset.badgeRequirement || '',
            badge_tier: badge.dataset.badgeTier || ''
        };
    }

    function currentShowcase() {
        return Array.from(section.querySelectorAll('[data-editor-slot]'))
            .map((slot) => ({
                slot_order: Number(slot.dataset.editorSlot || 0),
                badge_key: slot.dataset.badgeKey || ''
            }))
            .filter((item) => item.slot_order > 0 && item.badge_key !== '');
    }

    function renderReadOnlyShowcase(showcase) {
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
                        <img class="badge-icon" src="${badgeIconPath(badge.badge_key)}" alt="" loading="lazy" width="64" height="64">
                        <strong>${escapeHtml(badge.badge_name)}</strong>
                        <span>${escapeHtml(badge.badge_description)}</span>
                        <small>${escapeHtml(String(badge.badge_tier || '').replace(/^./, (first) => first.toUpperCase()))}</small>
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
    }

    function renderSlot(slot, badge) {
        const content = slot.querySelector('[data-editor-slot-content]');
        if (!content) return;

        if (!badge) {
            slot.dataset.badgeKey = '';
            content.innerHTML = '<span class="badge-showcase-empty">Open slot</span>';
            return;
        }

        slot.dataset.badgeKey = badge.badge_key;
        content.innerHTML = `
            <img class="badge-icon" src="${badgeIconPath(badge.badge_key)}" alt="" loading="lazy" width="52" height="52">
            <strong>${escapeHtml(badge.badge_name)}</strong>
        `;
    }

    function syncEarnedState() {
        const showcasedKeys = new Set(currentShowcase().map((item) => item.badge_key));
        section.querySelectorAll('[data-earned-badge]').forEach((badge) => {
            const isShowcased = showcasedKeys.has(badge.dataset.badgeKey || '');
            badge.classList.toggle('is-showcased', isShowcased);
            badge.setAttribute('aria-pressed', String(isShowcased));
        });
    }

    function filterEarnedBadges() {
        const query = String(earnedSearch?.value || '').trim().toLowerCase();
        let visibleCount = 0;

        section.querySelectorAll('[data-earned-badge]').forEach((badge) => {
            const haystack = [
                badge.dataset.badgeName,
                badge.dataset.badgeDescription,
                badge.dataset.badgeRequirement,
                badge.dataset.badgeTier,
                badge.dataset.badgeKey
            ].join(' ').toLowerCase();
            const isVisible = !query || haystack.includes(query);
            badge.hidden = !isVisible;
            if (isVisible) visibleCount += 1;
        });

        if (earnedEmpty) {
            earnedEmpty.hidden = visibleCount > 0;
        }
    }

    function setStatus(message, isError = false) {
        if (!status) return;
        if (statusTimer) {
            window.clearTimeout(statusTimer);
            statusTimer = null;
        }
        status.hidden = !message;
        status.textContent = message || '';
        status.classList.toggle('is-error', isError);
    }

    function setTemporaryStatus(message, isError = false, duration = 3000) {
        setStatus(message, isError);
        if (!message) return;
        statusTimer = window.setTimeout(() => {
            setStatus('');
        }, duration);
    }

    function placeBadge(slot, badgeKey) {
        const badge = badgeData(badgeKey);
        if (!slot || !badge) return;

        section.querySelectorAll('[data-editor-slot]').forEach((otherSlot) => {
            if (otherSlot !== slot && otherSlot.dataset.badgeKey === badge.badge_key) {
                renderSlot(otherSlot, null);
            }
        });
        renderSlot(slot, badge);
        syncEarnedState();
        setStatus('');
    }

    function firstOpenSlot() {
        return Array.from(section.querySelectorAll('[data-editor-slot]'))
            .find((slot) => !(slot.dataset.badgeKey || ''));
    }

    function addBadgeToOpenSlot(badgeKey) {
        if (!badgeKey) return;

        const isAlreadyShowcased = currentShowcase().some((item) => item.badge_key === badgeKey);
        if (isAlreadyShowcased) {
            setTemporaryStatus('That badge is already in your showcase.');
            return;
        }

        const slot = firstOpenSlot();
        if (!slot) {
            setTemporaryStatus("There's no open slots.", true);
            return;
        }

        placeBadge(slot, badgeKey);
    }

    function openEditor() {
        if (!editor) return;
        lockedScrollY = window.scrollY || document.documentElement.scrollTop || 0;
        editor.hidden = false;
        document.body.classList.add('has-modal-open');
        document.body.style.top = `-${lockedScrollY}px`;
        section.querySelector('[data-showcase-editor-save]')?.focus();
        syncEarnedState();
    }

    function closeEditor() {
        if (!editor) return;
        editor.hidden = true;
        document.body.classList.remove('has-modal-open');
        document.body.style.top = '';
        window.scrollTo(0, lockedScrollY);
        syncEarnedState();
    }

    function saveShowcase(button) {
        if (button instanceof HTMLButtonElement) {
            button.disabled = true;
        }
        setStatus('Saving...');

        postForm('update_badge_showcase.php', {
            csrf_token: csrfToken,
            action: 'save',
            showcase: JSON.stringify(currentShowcase())
        })
            .then((data) => {
                if (!data.ok) {
                    setStatus(data.message || 'Could not save badge showcase.', true);
                    return;
                }
                renderReadOnlyShowcase(data.showcase || []);
                closeEditor();
            })
            .catch(() => setStatus('Could not save badge showcase. Please try again.', true))
            .finally(() => {
                if (button instanceof HTMLButtonElement) {
                    button.disabled = false;
                }
            });
    }

    section.querySelector('[data-showcase-editor-open]')?.addEventListener('click', openEditor);
    section.querySelectorAll('[data-showcase-editor-close]').forEach((button) => {
        button.addEventListener('click', closeEditor);
    });
    section.querySelector('[data-showcase-editor-save]')?.addEventListener('click', (event) => {
        saveShowcase(event.currentTarget);
    });

    earnedSearch?.addEventListener('input', filterEarnedBadges);

    earnedList?.addEventListener('dragstart', (event) => {
        event.preventDefault();
    });

    earnedList?.addEventListener('click', (event) => {
        const badge = event.target instanceof Element ? event.target.closest('[data-earned-badge]') : null;
        if (!badge) return;
        event.preventDefault();
        addBadgeToOpenSlot(badge.dataset.badgeKey || '');
    });

    slotsWrap?.addEventListener('click', (event) => {
        const slot = event.target instanceof Element ? event.target.closest('[data-editor-slot]') : null;
        if (slot && (slot.dataset.badgeKey || '')) {
            renderSlot(slot, null);
            syncEarnedState();
            setStatus('');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && editor && !editor.hidden) {
            closeEditor();
        }
    });

    syncEarnedState();
    filterEarnedBadges();
};
window.CraftCrawlInitBadgeShowcase();
