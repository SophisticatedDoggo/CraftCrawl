window.CraftCrawlInitBusinessHoursEditor = function (root = document) {
    function setRowDisabledState(row) {
        const closedInput = row.querySelector('[data-hours-closed]');
        const openInput = row.querySelector('[data-hours-open]');
        const closeInput = row.querySelector('[data-hours-close]');

        if (!closedInput || !openInput || !closeInput) {
            return;
        }

        const isClosed = closedInput.checked;
        openInput.disabled = isClosed;
        closeInput.disabled = isClosed;
    }

    function selectedDayValues(editor) {
        return Array.from(editor.querySelectorAll('[data-hours-target-day]:checked'))
            .map((input) => input.value);
    }

    function setSelectedDays(editor, mode) {
        const dayInputs = Array.from(editor.querySelectorAll('[data-hours-target-day]'));

        dayInputs.forEach((input) => {
            const day = Number(input.value);

            if (mode === 'clear') {
                input.checked = false;
            } else if (mode === 'all') {
                input.checked = true;
            } else if (mode === 'weekdays') {
                input.checked = day >= 1 && day <= 5;
            } else if (mode === 'weekend') {
                input.checked = day === 0 || day === 6;
            }
        });
    }

    function applyBulkHours(editor) {
        const openValue = editor.querySelector('[data-hours-template-open]')?.value || '';
        const closeValue = editor.querySelector('[data-hours-template-close]')?.value || '';
        const isClosed = Boolean(editor.querySelector('[data-hours-template-closed]')?.checked);
        const days = selectedDayValues(editor);

        days.forEach((day) => {
            const row = editor.querySelector('[data-hours-row="' + day + '"]');

            if (!row) {
                return;
            }

            const closedInput = row.querySelector('[data-hours-closed]');
            const openInput = row.querySelector('[data-hours-open]');
            const closeInput = row.querySelector('[data-hours-close]');

            if (closedInput) {
                closedInput.checked = isClosed;
            }

            if (openInput) {
                openInput.value = isClosed ? '' : openValue;
            }

            if (closeInput) {
                closeInput.value = isClosed ? '' : closeValue;
            }

            setRowDisabledState(row);
        });
    }

    root.querySelectorAll('.business-hours-editor').forEach((editor) => {
        if (editor.dataset.shellReady === 'true') return;
        editor.dataset.shellReady = 'true';
        editor.querySelectorAll('[data-hours-row]').forEach((row) => {
            setRowDisabledState(row);
            row.querySelector('[data-hours-closed]')?.addEventListener('change', () => {
                setRowDisabledState(row);
            });
        });

        editor.querySelectorAll('[data-hours-select]').forEach((button) => {
            button.addEventListener('click', () => {
                setSelectedDays(editor, button.dataset.hoursSelect);
            });
        });

        editor.querySelector('[data-hours-apply]')?.addEventListener('click', () => {
            applyBulkHours(editor);
        });
    });
};
window.CraftCrawlInitBusinessHoursEditor();
