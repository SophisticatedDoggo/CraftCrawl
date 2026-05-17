function focusFeedThreadHashTarget() {
    if (!window.location.hash) {
        return;
    }

    const target = document.getElementById(window.location.hash.slice(1));
    if (!target) {
        return;
    }

    window.requestAnimationFrame(() => {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.classList.add('notification-focus-target');
    });
}

window.CraftCrawlInitFeedThread = function (root = document) {
    root.querySelectorAll('[data-reply-toggle]').forEach((button) => {
        if (button.dataset.replyToggleReady === 'true') return;
        button.dataset.replyToggleReady = 'true';
        button.addEventListener('click', () => {
            const form = document.getElementById(button.getAttribute('aria-controls'));
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!isExpanded));
            button.textContent = isExpanded ? 'Reply' : 'Hide Reply';
            if (form) {
                form.hidden = isExpanded;
                if (!isExpanded) form.querySelector('textarea')?.focus();
            }
        });
    });
    root.querySelectorAll('[data-reply-cancel]').forEach((button) => {
        if (button.dataset.replyCancelReady === 'true') return;
        button.dataset.replyCancelReady = 'true';
        button.addEventListener('click', () => {
            const form = button.closest('.feed-reply-form');
            const toggle = form ? document.querySelector(`[aria-controls="${form.id}"]`) : null;
            if (form) form.hidden = true;
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
                toggle.textContent = 'Reply';
            }
        });
    });

    focusFeedThreadHashTarget();
};
window.CraftCrawlInitFeedThread();
