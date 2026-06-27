(function () {
    'use strict';

    var activeMenu = null;

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    function closeActive() {
        if (!activeMenu) return;
        var dropdown = activeMenu.querySelector('.post-menu-dropdown');
        if (dropdown) dropdown.classList.remove('is-open');
        var trigger = activeMenu.querySelector('.post-menu-trigger');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        activeMenu = null;
    }

    function openMenu(container) {
        closeActive();
        var dropdown = container.querySelector('.post-menu-dropdown');
        if (!dropdown) return;
        dropdown.classList.add('is-open');
        var trigger = container.querySelector('.post-menu-trigger');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
        activeMenu = container;

        var rect = dropdown.getBoundingClientRect();
        if (rect.bottom > window.innerHeight) {
            dropdown.style.top = 'auto';
            dropdown.style.bottom = '100%';
        }
    }

    function toggleMenu(container) {
        if (activeMenu === container) {
            closeActive();
        } else {
            openMenu(container);
        }
    }

    function buildMenuItems(options) {
        var items = [];
        if (!options.isSelf) {
            items.push({
                label: 'Report',
                action: 'report',
                className: ''
            });
        }
        return items;
    }

    function renderMenuHtml(options) {
        var items = buildMenuItems(options);
        if (items.length === 0) return '';

        var contentType = escapeHtml(options.contentType || 'feed_post');
        var contentId = escapeHtml(options.contentId || '');
        var contentLabel = escapeHtml(options.contentLabel || '');

        var html = '<div class="post-menu" data-post-menu data-content-type="' + contentType + '" data-content-id="' + contentId + '" data-content-label="' + contentLabel + '">';
        html += '<button type="button" class="post-menu-trigger" aria-expanded="false" aria-label="More options">';
        html += '<span class="post-menu-trigger-icon" aria-hidden="true"></span>';
        html += '</button>';
        html += '<div class="post-menu-dropdown">';
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            html += '<button type="button" class="post-menu-dropdown-item' + (item.className ? ' ' + item.className : '') + '" data-post-menu-action="' + escapeHtml(item.action) + '">';
            html += escapeHtml(item.label);
            html += '</button>';
        }
        html += '</div></div>';
        return html;
    }

    function handleMenuAction(container, action) {
        closeActive();
        if (action === 'report') {
            var contentType = container.getAttribute('data-content-type') || 'feed_post';
            var contentId = container.getAttribute('data-content-id') || '';
            var contentLabel = container.getAttribute('data-content-label') || '';
            if (window.CraftCrawlReportModal) {
                window.CraftCrawlReportModal.open({
                    contentType: contentType,
                    contentId: contentId,
                    contentLabel: contentLabel
                });
            }
        }
        if (action === 'profile_remove') {
            var formAction = container.getAttribute('data-profile-action');
            var locationId = container.getAttribute('data-profile-location-id');
            if (!formAction || !locationId) return;
            var csrfToken = document.querySelector('[data-csrf-token]');
            if (!csrfToken) return;
            var form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            form.innerHTML = '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken.getAttribute('data-csrf-token')) + '">'
                + '<input type="hidden" name="form_action" value="' + escapeHtml(formAction) + '">'
                + '<input type="hidden" name="location_id" value="' + escapeHtml(locationId) + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('.post-menu-trigger');
        if (trigger) {
            e.preventDefault();
            e.stopPropagation();
            var container = trigger.closest('[data-post-menu]');
            if (container) toggleMenu(container);
            return;
        }

        var actionBtn = e.target.closest('[data-post-menu-action]');
        if (actionBtn) {
            e.preventDefault();
            e.stopPropagation();
            var menuContainer = actionBtn.closest('[data-post-menu]');
            var actionName = actionBtn.getAttribute('data-post-menu-action');
            if (menuContainer && actionName) handleMenuAction(menuContainer, actionName);
            return;
        }

        if (activeMenu && !e.target.closest('[data-post-menu]')) {
            closeActive();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && activeMenu) {
            closeActive();
        }
    });

    function resolveContentType(itemType) {
        if (itemType === 'business_post') return 'business_post';
        return 'feed_post';
    }

    window.CraftCrawlPostMenu = {
        renderTrigger: function (options) {
            if (options.isSelf) return '';
            return renderMenuHtml({
                contentType: options.contentType || resolveContentType(options.itemType || ''),
                contentId: options.contentId || options.itemKey || '',
                contentLabel: options.contentLabel || '',
                isSelf: false
            });
        },

        init: function (root) {
            // no-op: event delegation handles everything
        },

        close: function () {
            closeActive();
        }
    };
}());
