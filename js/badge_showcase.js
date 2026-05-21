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
    let selectedBadgeKey = '';

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
            content.innerHTML = '<span class="badge-showcase-empty">Drop badge here</span>';
            return;
        }

        slot.dataset.badgeKey = badge.badge_key;
        content.innerHTML = `
            <img class="badge-icon" src="${badgeIconPath(badge.badge_key)}" alt="" loading="lazy" width="52" height="52">
            <strong>${escapeHtml(badge.badge_name)}</strong>
            <button type="button" class="badge-showcase-editor-remove" data-editor-remove>Remove</button>
        `;
    }

    function syncEarnedState() {
        const showcasedKeys = new Set(currentShowcase().map((item) => item.badge_key));
        section.querySelectorAll('[data-earned-badge]').forEach((badge) => {
            const isShowcased = showcasedKeys.has(badge.dataset.badgeKey || '');
            badge.classList.toggle('is-showcased', isShowcased);
            badge.setAttribute('aria-pressed', String((badge.dataset.badgeKey || '') === selectedBadgeKey));
        });
    }

    function setStatus(message, isError = false) {
        if (!status) return;
        status.hidden = !message;
        status.textContent = message || '';
        status.classList.toggle('is-error', isError);
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

    function openEditor() {
        if (!editor) return;
        editor.hidden = false;
        document.body.classList.add('has-modal-open');
        section.querySelector('[data-showcase-editor-save]')?.focus();
        syncEarnedState();
    }

    function closeEditor() {
        if (!editor) return;
        editor.hidden = true;
        document.body.classList.remove('has-modal-open');
        selectedBadgeKey = '';
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

    earnedList?.addEventListener('dragstart', (event) => {
        const badge = event.target instanceof Element ? event.target.closest('[data-earned-badge]') : null;
        if (!badge) return;
        event.dataTransfer.effectAllowed = 'copyMove';
        event.dataTransfer.setData('text/plain', badge.dataset.badgeKey || '');
    });

    earnedList?.addEventListener('click', (event) => {
        const badge = event.target instanceof Element ? event.target.closest('[data-earned-badge]') : null;
        if (!badge) return;
        selectedBadgeKey = selectedBadgeKey === badge.dataset.badgeKey ? '' : (badge.dataset.badgeKey || '');
        syncEarnedState();
        setStatus(selectedBadgeKey ? 'Choose a showcase slot for this badge.' : '');
    });

    slotsWrap?.addEventListener('dragover', (event) => {
        const slot = event.target instanceof Element ? event.target.closest('[data-editor-slot]') : null;
        if (!slot) return;
        event.preventDefault();
        slot.classList.add('is-drag-over');
    });

    slotsWrap?.addEventListener('dragleave', (event) => {
        const slot = event.target instanceof Element ? event.target.closest('[data-editor-slot]') : null;
        if (slot) slot.classList.remove('is-drag-over');
    });

    slotsWrap?.addEventListener('drop', (event) => {
        const slot = event.target instanceof Element ? event.target.closest('[data-editor-slot]') : null;
        if (!slot) return;
        event.preventDefault();
        slot.classList.remove('is-drag-over');
        placeBadge(slot, event.dataTransfer.getData('text/plain'));
    });

    slotsWrap?.addEventListener('click', (event) => {
        const remove = event.target instanceof Element ? event.target.closest('[data-editor-remove]') : null;
        if (remove) {
            renderSlot(remove.closest('[data-editor-slot]'), null);
            syncEarnedState();
            return;
        }

        const slot = event.target instanceof Element ? event.target.closest('[data-editor-slot]') : null;
        if (slot && selectedBadgeKey) {
            placeBadge(slot, selectedBadgeKey);
            selectedBadgeKey = '';
            syncEarnedState();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && editor && !editor.hidden) {
            closeEditor();
        }
    });

    syncEarnedState();
};
window.CraftCrawlInitBadgeShowcase();
