function notificationFocusTarget(root = document) {
    const params = new URLSearchParams(window.location.search);
    const commentId = params.get('focus_comment');
    const section = params.get('focus_section');

    if (commentId && /^\d+$/.test(commentId)) {
        return root.querySelector(`#comment-${CSS.escape(commentId)}`);
    }

    if (section === 'reactions') {
        return root.querySelector('#feed-reactions');
    }

    if (window.location.hash) {
        return root.querySelector(`#${CSS.escape(window.location.hash.slice(1))}`);
    }

    return null;
}

function focusNotificationTargetWhenVisible(target) {
    if (!target || target.dataset.notificationFocusHandled === 'true') {
        return;
    }

    target.dataset.notificationFocusHandled = 'true';
    target.classList.add('notification-focus-pending');

    const highlightTarget = () => {
        target.classList.remove('notification-focus-pending');
        target.classList.add('notification-focus-target');
    };

    const observer = new IntersectionObserver((entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) {
            return;
        }

        observer.disconnect();
        highlightTarget();
    }, { threshold: 0.45 });

    observer.observe(target);

    window.requestAnimationFrame(() => {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    window.setTimeout(() => {
        observer.disconnect();
        if (!target.classList.contains('notification-focus-target')) {
            highlightTarget();
        }
    }, 1800);
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

    root.querySelectorAll('.feed-comment-form').forEach((form) => {
        if (form.dataset.shellCommentReady === 'true') return;
        form.dataset.shellCommentReady = 'true';
        form.addEventListener('submit', (event) => {
            if (typeof window.CraftCrawlNavigateUserShell !== 'function' || form.dataset.shellSubmitting === 'true') {
                return;
            }

            event.preventDefault();
            form.dataset.shellSubmitting = 'true';
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
            });

            fetch(form.action || window.location.href, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'CraftCrawlShell' }
            })
                .then((response) => {
                    if (!response.ok) throw new Error('Comment could not be posted.');
                    const nextUrl = response.url || window.location.href;
                    return window.CraftCrawlNavigateUserShell(nextUrl, {
                        replace: true,
                        noStore: true
                    }).then((handled) => {
                        if (!handled) window.location.href = nextUrl;
                    });
                })
                .catch(() => {
                    HTMLFormElement.prototype.submit.call(form);
                })
                .finally(() => {
                    form.dataset.shellSubmitting = 'false';
                    form.querySelectorAll('button[type="submit"]').forEach((button) => {
                        button.disabled = false;
                    });
                });
        });
    });

    focusNotificationTargetWhenVisible(notificationFocusTarget(root));
};
window.CraftCrawlInitFeedThread();
