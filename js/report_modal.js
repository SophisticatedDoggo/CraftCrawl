(function () {
    'use strict';

    var modal = null;
    var currentOptions = null;

    var reportTypesByContent = {
        feed_post: [
            { value: 'spam', label: 'Spam or unwanted content', hint: 'What makes this spam?' },
            { value: 'inappropriate', label: 'Inappropriate or offensive content', hint: 'What is inappropriate about it?' },
            { value: 'harassment', label: 'Harassment or bullying', hint: 'Please describe the harassment.' },
            { value: 'misleading', label: 'Misleading or false information', hint: 'What is misleading?' },
            { value: 'other', label: 'Other', hint: 'Please describe the issue.' }
        ],
        business_post: [
            { value: 'spam', label: 'Spam or unwanted content', hint: 'What makes this spam?' },
            { value: 'inappropriate', label: 'Inappropriate or offensive content', hint: 'What is inappropriate about it?' },
            { value: 'misleading', label: 'Misleading or false information', hint: 'What is misleading?' },
            { value: 'other', label: 'Other', hint: 'Please describe the issue.' }
        ],
        event: [
            { value: 'spam', label: 'Spam or unwanted content', hint: 'What makes this spam?' },
            { value: 'inappropriate', label: 'Inappropriate or offensive content', hint: 'What is inappropriate about it?' },
            { value: 'misleading', label: 'Misleading or false information', hint: 'What is misleading?' },
            { value: 'cancelled', label: 'Event has been cancelled', hint: 'How do you know it was cancelled?' },
            { value: 'wrong_details', label: 'Event details are incorrect', hint: 'What details are wrong?' },
            { value: 'other', label: 'Other', hint: 'Please describe the issue.' }
        ],
        user: [
            { value: 'spam', label: 'Spamming or unwanted behavior', hint: 'What makes this spam?' },
            { value: 'harassment', label: 'Harassment or bullying', hint: 'Please describe the harassment.' },
            { value: 'impersonation', label: 'Pretending to be someone else', hint: 'Who are they impersonating?' },
            { value: 'inappropriate', label: 'Inappropriate behavior or content', hint: 'What is inappropriate?' },
            { value: 'other', label: 'Other', hint: 'Please describe the issue.' }
        ]
    };

    var titlesByContent = {
        feed_post: 'Report this post',
        business_post: 'Report this post',
        event: 'Report this event',
        user: 'Report this user'
    };

    var subtitlesByContent = {
        feed_post: 'Help us keep CraftCrawl safe. What’s the issue?',
        business_post: 'Help us keep CraftCrawl safe. What’s the issue?',
        event: 'Help us keep CraftCrawl accurate. What’s the issue?',
        user: 'Help us keep CraftCrawl safe. What’s the issue with this user?'
    };

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    function getCsrfToken() {
        var input = document.querySelector('input[name="csrf_token"]');
        if (input) return input.value;
        var el = document.querySelector('[data-csrf-token]');
        if (el) return el.getAttribute('data-csrf-token');
        return '';
    }

    function buildModal() {
        var el = document.createElement('div');
        el.className = 'welcome-modal report-listing-modal report-content-modal';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-labelledby', 'report-content-modal-title');
        el.hidden = true;
        el.innerHTML =
            '<div class="welcome-modal-backdrop" data-report-content-backdrop aria-hidden="true"></div>' +
            '<div class="welcome-modal-panel report-listing-modal-panel" data-report-content-panel></div>';
        document.body.appendChild(el);
        return el;
    }

    function renderForm(options) {
        var contentType = options.contentType || 'feed_post';
        var types = reportTypesByContent[contentType] || reportTypesByContent.feed_post;
        var title = titlesByContent[contentType] || 'Report';
        var subtitle = subtitlesByContent[contentType] || '';

        var html = '<h2 id="report-content-modal-title">' + escapeHtml(title) + '</h2>';
        if (subtitle) {
            html += '<p class="report-modal-subtitle">' + escapeHtml(subtitle) + '</p>';
        }
        html += '<form method="POST" action="' + escapeHtml(getReportUrl()) + '" data-report-content-form>';
        html += '<input type="hidden" name="csrf_token" value="' + escapeHtml(getCsrfToken()) + '">';
        html += '<input type="hidden" name="content_type" value="' + escapeHtml(contentType) + '">';
        html += '<input type="hidden" name="content_id" value="' + escapeHtml(options.contentId || '') + '">';
        html += '<div class="report-type-list">';
        for (var i = 0; i < types.length; i++) {
            var t = types[i];
            html += '<label class="report-type-option">';
            html += '<input type="radio" name="report_type" value="' + escapeHtml(t.value) + '" required>';
            html += '<span class="report-type-label">' + escapeHtml(t.label) + '</span>';
            html += '<span class="report-type-hint">' + escapeHtml(t.hint) + '</span>';
            html += '</label>';
        }
        html += '</div>';
        html += '<div class="report-details-field" data-report-content-details hidden>';
        html += '<label for="report_content_details">Additional details <span data-report-content-optional>(optional)</span></label>';
        html += '<textarea id="report_content_details" name="details" maxlength="1000" rows="3" placeholder="Add any helpful details..."></textarea>';
        html += '</div>';
        html += '<div class="report-modal-actions">';
        html += '<button type="submit" class="report-modal-submit" data-report-content-submit disabled>Submit Report</button>';
        html += '<button type="button" class="report-modal-cancel" data-report-content-close>Cancel</button>';
        html += '</div>';
        html += '</form>';
        return html;
    }

    function getReportUrl() {
        var path = window.location.pathname;
        if (path.indexOf('/user/') !== -1) {
            return 'report_content.php';
        }
        return 'user/report_content.php';
    }

    function openModal(options) {
        if (!modal) modal = buildModal();
        currentOptions = options;
        var panel = modal.querySelector('[data-report-content-panel]');
        panel.innerHTML = renderForm(options);
        modal.hidden = false;
        document.body.classList.add('welcome-modal-open');

        var firstRadio = modal.querySelector('input[type="radio"]');
        if (firstRadio) firstRadio.focus();
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.add('is-closing');
        document.body.classList.remove('welcome-modal-open');
        window.setTimeout(function () {
            modal.hidden = true;
            modal.classList.remove('is-closing');
        }, 180);
        currentOptions = null;
    }

    function showSuccess() {
        var panel = modal.querySelector('[data-report-content-panel]');
        panel.innerHTML =
            '<h2 class="report-success-title">Report submitted</h2>' +
            '<p class="report-success-body">Thanks for letting us know. We’ll review this and take any needed action.</p>' +
            '<button type="button" class="report-success-close" data-report-content-close>Close</button>';
        var closeBtn = panel.querySelector('[data-report-content-close]');
        if (closeBtn) closeBtn.focus();
    }

    function showError(msg) {
        var form = modal.querySelector('[data-report-content-form]');
        if (!form) return;
        var existing = form.querySelector('.report-error-msg');
        if (existing) existing.remove();
        var p = document.createElement('p');
        p.className = 'report-error-msg form-message form-message-error';
        var messages = {
            already_submitted: 'You already have a pending report for this content.',
            details_required: 'Please add a few details for that report type.',
            not_found: 'This content could not be found.',
            cannot_report_self: 'You cannot report your own content.',
            invalid_report: 'Please select a valid report type.'
        };
        p.textContent = messages[msg] || 'Something went wrong. Please try again.';
        form.insertBefore(p, form.firstChild);
        var submitBtn = form.querySelector('[data-report-content-submit]');
        if (submitBtn) submitBtn.disabled = false;
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-report-content-backdrop]') || e.target.closest('[data-report-content-close]')) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && !modal.hidden) {
            closeModal();
        }
    });

    document.addEventListener('change', function (e) {
        if (!modal || modal.hidden) return;
        if (e.target.name !== 'report_type') return;
        var detailsField = modal.querySelector('[data-report-content-details]');
        var submitBtn = modal.querySelector('[data-report-content-submit]');
        if (detailsField) detailsField.hidden = false;
        if (submitBtn) submitBtn.disabled = false;
    });

    document.addEventListener('submit', function (e) {
        if (!e.target.matches || !e.target.matches('[data-report-content-form]')) return;
        e.preventDefault();
        var form = e.target;
        var submitBtn = form.querySelector('[data-report-content-submit]');
        if (submitBtn) submitBtn.disabled = true;

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.ok) {
                    showSuccess();
                } else {
                    showError(data.message);
                }
            })
            .catch(function () { showError(''); });
    });

    window.CraftCrawlReportModal = {
        open: openModal,
        close: closeModal
    };
}());
