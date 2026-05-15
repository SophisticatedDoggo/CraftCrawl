window.CraftCrawlInitBusinessPosts = function (root = document) {
    root.querySelectorAll('[data-post-create-tab]').forEach((tab) => {
        if (tab.dataset.postCreateReady === 'true') return;
        tab.dataset.postCreateReady = 'true';
        tab.addEventListener('click', () => {
            const type = tab.dataset.postCreateTab;
            root.querySelectorAll('[data-post-create-tab]').forEach((candidate) => candidate.classList.toggle('is-active', candidate === tab));
            root.querySelectorAll('[data-post-create-form]').forEach((form) => { form.hidden = form.dataset.postCreateForm !== type; });
        });
    });
    root.querySelectorAll('[data-post-edit-toggle]').forEach((button) => {
        if (button.dataset.postEditReady === 'true') return;
        button.dataset.postEditReady = 'true';
        button.addEventListener('click', () => {
            const form = document.getElementById(button.getAttribute('aria-controls'));
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!isExpanded));
            button.textContent = isExpanded ? 'Edit' : 'Cancel';
            if (form) form.hidden = isExpanded;
        });
    });
    root.querySelectorAll('[data-post-edit-cancel]').forEach((button) => {
        if (button.dataset.postEditCancelReady === 'true') return;
        button.dataset.postEditCancelReady = 'true';
        button.addEventListener('click', () => {
            const form = button.closest('.portal-post-edit-form');
            const toggle = form ? document.querySelector(`[aria-controls="${form.id}"]`) : null;
            if (form) form.hidden = true;
            if (toggle) { toggle.setAttribute('aria-expanded', 'false'); toggle.textContent = 'Edit'; }
        });
    });
    root.querySelectorAll('[data-comment-toggle]').forEach((button) => {
        if (button.dataset.commentToggleReady === 'true') return;
        button.dataset.commentToggleReady = 'true';
        button.addEventListener('click', () => {
            const form = document.getElementById(button.getAttribute('aria-controls'));
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!isExpanded));
            button.textContent = isExpanded ? 'Add a Comment' : 'Cancel';
            if (form) { form.hidden = isExpanded; if (!isExpanded) form.querySelector('textarea')?.focus(); }
        });
    });
    root.querySelectorAll('[data-comment-cancel]').forEach((button) => {
        if (button.dataset.commentCancelReady === 'true') return;
        button.dataset.commentCancelReady = 'true';
        button.addEventListener('click', () => {
            const form = button.closest('.portal-reply-form');
            const toggle = form ? document.querySelector(`[aria-controls="${form.id}"]`) : null;
            if (form) form.hidden = true;
            if (toggle) { toggle.setAttribute('aria-expanded', 'false'); toggle.textContent = 'Add a Comment'; }
        });
    });
};
window.CraftCrawlInitBusinessPosts();
