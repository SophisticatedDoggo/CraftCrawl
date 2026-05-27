window.CraftCrawlInitAdminReviewCenter = function (root = document) {
    const reviewRoot = root.querySelector?.('[data-admin-review-center]')
        || (root.matches?.('[data-admin-review-center]') ? root : null);
    if (!reviewRoot) return;

    const batchActionValues = new Set([
        'approve_new_location',
        'reject_new_location',
        'more_info_new_location',
        'approve_claim',
        'reject_claim',
        'more_info_claim',
        'cancel_claim',
        'approve_suggestion',
        'reject_suggestion',
        'duplicate_suggestion',
        'approve_import',
        'reject_import',
        'mark_import_duplicate',
        'enable_checkins',
        'disable_location',
        'reenable_location',
        'hide_location',
        'unhide_location',
        'restore_suggestion',
        'review_report_hide_location',
        'review_report_disable_location'
    ]);

    function currentScrollY() {
        return window.scrollY || document.documentElement.scrollTop || 0;
    }

    function replaceReviewContent(html, url, scrollY) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const next = doc.querySelector('[data-area-page-content]');
        const current = document.querySelector('[data-area-page-content]');
        if (!next || !current) throw new Error('Review center could not refresh.');

        current.replaceWith(next);
        document.title = doc.title;
        window.history.replaceState(window.history.state, '', url || window.location.href);
        window.CraftCrawlInitAdminReviewCenter?.(next);
        window.CraftCrawlInitMobileActionsMenu?.();
        window.requestAnimationFrame(() => window.scrollTo(0, scrollY));
    }

    function postFormData(form, formData) {
        return window.fetch(form.action || window.location.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then((response) => {
            if (!response.ok) throw new Error('Review action failed.');
            return response.text().then((html) => ({ html, url: response.url }));
        });
    }

    function formDataForAction(form, actionValue) {
        const formData = new FormData(form);
        formData.set('form_action', actionValue);
        return formData;
    }

    function setFormDisabled(form, disabled) {
        form.querySelectorAll('button, input:not([type="hidden"]), select, textarea').forEach((control) => {
            control.disabled = disabled;
        });
        if (form.id) {
            document.querySelectorAll(`[form="${CSS.escape(form.id)}"]`).forEach((control) => {
                control.disabled = disabled;
            });
        }
    }

    function updateBatchToolbar(section) {
        const selected = section.querySelectorAll('[data-admin-batch-select]:checked').length;
        const count = section.querySelector('[data-admin-batch-count]');
        const buttons = section.querySelectorAll('[data-admin-batch-action]');
        if (count) {
            count.textContent = selected === 1 ? '1 selected' : `${selected} selected`;
        }
        buttons.forEach((button) => {
            button.disabled = selected === 0 || section.dataset.adminBatchBusy === 'true';
        });
    }

    function selectedForms(section) {
        return [...section.querySelectorAll('[data-admin-batch-select]:checked')]
            .map((input) => input.closest('[data-admin-review-row], .admin-list-item')?.querySelector('form[method="POST"]'))
            .filter(Boolean);
    }

    async function runBatchAction(section, actionValue) {
        const forms = selectedForms(section);
        if (forms.length === 0) return;

        const scrollY = currentScrollY();
        section.dataset.adminBatchBusy = 'true';
        updateBatchToolbar(section);

        let lastPayload = null;
        try {
            for (const form of forms) {
                lastPayload = await postFormData(form, formDataForAction(form, actionValue));
            }
            if (lastPayload) {
                replaceReviewContent(lastPayload.html, lastPayload.url, scrollY);
            }
        } catch (_) {
            window.location.reload();
        } finally {
            section.dataset.adminBatchBusy = 'false';
        }
    }

    function actionButtonsForSection(section) {
        const actions = new Map();
        section.querySelectorAll('form[method="POST"] button[name="form_action"], button[name="form_action"][form]').forEach((button) => {
            if (!batchActionValues.has(button.value) || actions.has(button.value)) return;
            actions.set(button.value, button.textContent.trim() || button.value);
        });
        return actions;
    }

    function cellSortValue(row, columnIndex) {
        const cell = row.cells[columnIndex];
        if (!cell) return '';
        const control = cell.querySelector('select, textarea, input:not([type="hidden"]):not([type="checkbox"])');
        const rawValue = control ? control.value : cell.textContent;
        return (rawValue || '').replace(/\s+/g, ' ').trim();
    }

    function comparableNumber(value) {
        const match = value.match(/-?\d+(?:\.\d+)?/);
        return match ? Number(match[0]) : null;
    }

    function enhanceSortableTables(container) {
        const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
        container.querySelectorAll('table').forEach((table) => {
            if (table.dataset.adminSortableReady === 'true') return;
            const headerRow = table.tHead?.rows?.[0];
            const body = table.tBodies?.[0];
            if (!headerRow || !body) return;

            table.dataset.adminSortableReady = 'true';
            const headers = [...headerRow.cells];

            headers.forEach((header, columnIndex) => {
                const label = header.textContent.replace(/\s+/g, ' ').trim();
                if (label === '' || label === 'Select' || label === 'Actions') return;

                header.setAttribute('aria-sort', 'none');
                header.textContent = '';

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'admin-sort-header';
                button.dataset.adminSortColumn = String(columnIndex);
                button.setAttribute('aria-label', `Sort by ${label}`);

                const labelSpan = document.createElement('span');
                labelSpan.textContent = label;
                const indicator = document.createElement('span');
                indicator.className = 'admin-sort-indicator';
                indicator.setAttribute('aria-hidden', 'true');
                indicator.textContent = 'sort';

                button.append(labelSpan, indicator);
                header.appendChild(button);

                button.addEventListener('click', () => {
                    const nextDirection = table.dataset.adminSortColumn === String(columnIndex)
                        && table.dataset.adminSortDirection === 'asc'
                        ? 'desc'
                        : 'asc';

                    table.dataset.adminSortColumn = String(columnIndex);
                    table.dataset.adminSortDirection = nextDirection;

                    headers.forEach((candidate) => {
                        candidate.setAttribute('aria-sort', candidate === header
                            ? (nextDirection === 'asc' ? 'ascending' : 'descending')
                            : 'none');
                        const candidateIndicator = candidate.querySelector('.admin-sort-indicator');
                        if (candidateIndicator) {
                            candidateIndicator.textContent = candidate === header ? nextDirection : 'sort';
                        }
                    });

                    const sortedRows = [...body.rows]
                        .map((row, originalIndex) => ({ row, originalIndex }))
                        .sort((left, right) => {
                            const leftValue = cellSortValue(left.row, columnIndex);
                            const rightValue = cellSortValue(right.row, columnIndex);
                            const leftNumber = comparableNumber(leftValue);
                            const rightNumber = comparableNumber(rightValue);
                            let result;

                            if (leftNumber !== null && rightNumber !== null) {
                                result = leftNumber - rightNumber;
                            } else {
                                result = collator.compare(leftValue, rightValue);
                            }

                            if (result === 0) {
                                result = left.originalIndex - right.originalIndex;
                            }

                            return nextDirection === 'asc' ? result : -result;
                        })
                        .map(({ row }) => row);

                    body.append(...sortedRows);
                });
            });
        });
    }

    function enhanceSection(section) {
        if (section.dataset.adminBatchReady === 'true') return;
        const tableItems = [...section.querySelectorAll('[data-admin-review-row]')]
            .filter((item) => item.querySelector('form[method="POST"]'));
        const listItems = [...section.querySelectorAll(':scope > .admin-list-item')]
            .filter((item) => item.querySelector('form[method="POST"]'));
        const items = tableItems.length > 0 ? tableItems : listItems;
        const actions = actionButtonsForSection(section);
        if (items.length === 0 || actions.size === 0) return;

        section.dataset.adminBatchReady = 'true';
        section.classList.add('admin-review-list-panel');

        const toolbar = document.createElement('div');
        toolbar.className = 'admin-batch-toolbar';

        const selectAllLabel = document.createElement('label');
        selectAllLabel.className = 'admin-batch-select-all';
        const selectAll = document.createElement('input');
        selectAll.type = 'checkbox';
        selectAllLabel.append(selectAll, document.createTextNode(' Select all'));

        const count = document.createElement('span');
        count.dataset.adminBatchCount = 'true';
        count.textContent = '0 selected';

        const actionsWrap = document.createElement('div');
        actionsWrap.className = 'admin-batch-actions';
        actions.forEach((label, value) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.adminBatchAction = value;
            button.textContent = label;
            button.disabled = true;
            button.addEventListener('click', () => runBatchAction(section, value));
            actionsWrap.appendChild(button);
        });

        toolbar.append(selectAllLabel, count, actionsWrap);
        const searchForm = section.querySelector(':scope > form.admin-search-form');
        if (searchForm) {
            searchForm.after(toolbar);
        } else {
            section.querySelector('h2')?.after(toolbar);
        }

        function syncBatchSelectionState() {
            const selectableItems = items.filter((candidate) => candidate.querySelector('[data-admin-batch-select]'));
            const selectedItems = selectableItems.filter((candidate) => candidate.querySelector('[data-admin-batch-select]')?.checked);
            selectAll.checked = selectableItems.length > 0 && selectedItems.length === selectableItems.length;
            selectAll.indeterminate = selectedItems.length > 0 && selectedItems.length < selectableItems.length;
            updateBatchToolbar(section);
        }

        items.forEach((item) => {
            if (!item.matches('[data-admin-review-row]')) {
                item.classList.add('admin-review-list-row');
            }
            let input = item.querySelector('[data-admin-batch-select]');
            if (!input) {
                const label = document.createElement('label');
                label.className = 'admin-batch-row-select';
                input = document.createElement('input');
                input.type = 'checkbox';
                input.dataset.adminBatchSelect = 'true';
                label.append(input);
                item.prepend(label);
            }
            if (input.dataset.adminBatchChangeReady !== 'true') {
                input.dataset.adminBatchChangeReady = 'true';
                input.addEventListener('change', syncBatchSelectionState);
            }
        });

        section.querySelectorAll('.admin-review-list-row textarea[name="admin_notes"], [data-admin-review-row] textarea[name="admin_notes"]').forEach((textarea) => {
            if (textarea.dataset.adminExpandableNotesReady === 'true') return;
            textarea.dataset.adminExpandableNotesReady = 'true';
            textarea.setAttribute('aria-label', textarea.getAttribute('aria-label') || 'Admin notes');
            textarea.addEventListener('focus', () => {
                textarea.closest('[data-admin-review-row], .admin-review-list-row')?.classList.add('is-editing-notes');
            });
            textarea.addEventListener('blur', () => {
                textarea.closest('[data-admin-review-row], .admin-review-list-row')?.classList.remove('is-editing-notes');
            });
        });

        selectAll.addEventListener('change', () => {
            items.forEach((item) => {
                const input = item.querySelector('[data-admin-batch-select]');
                if (input) input.checked = selectAll.checked;
            });
            selectAll.indeterminate = false;
            updateBatchToolbar(section);
        });
        syncBatchSelectionState();
    }

    root.querySelectorAll('form[method="POST"]').forEach((form) => {
        if (form.dataset.adminReviewAjaxReady === 'true') return;
        form.dataset.adminReviewAjaxReady = 'true';

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const submitter = event.submitter;
            const formData = new FormData(form);
            const previousScrollY = currentScrollY();
            let submitterFallbackInput = null;

            if (submitter?.name) {
                formData.set(submitter.name, submitter.value);
                submitterFallbackInput = document.createElement('input');
                submitterFallbackInput.type = 'hidden';
                submitterFallbackInput.name = submitter.name;
                submitterFallbackInput.value = submitter.value;
                form.appendChild(submitterFallbackInput);
            }

            setFormDisabled(form, true);

            postFormData(form, formData)
                .then(({ html, url }) => {
                    replaceReviewContent(html, url, previousScrollY);
                })
                .catch(() => {
                    setFormDisabled(form, false);
                    HTMLFormElement.prototype.submit.call(form);
                });
        });
    });

    reviewRoot.querySelectorAll('.admin-panel').forEach(enhanceSection);
    enhanceSortableTables(reviewRoot);
};

window.CraftCrawlInitAdminReviewCenter();
