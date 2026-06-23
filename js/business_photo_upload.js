window.CraftCrawlInitBusinessPhotoUpload = function (root = document) {
    const form = root.querySelector('[data-photo-upload-form]');
    if (!form || form.dataset.photoUploadReady === 'true') return;
    form.dataset.photoUploadReady = 'true';

    const dropZone = form.querySelector('[data-photo-drop-zone]');
    const fileInput = form.querySelector('[data-photo-file-input]');
    const previews = form.querySelector('[data-photo-previews]');
    const actions = form.querySelector('[data-photo-upload-actions]');
    const countDisplay = form.querySelector('[data-photo-count]');
    const clearButton = form.querySelector('[data-photo-clear]');
    if (!dropZone || !fileInput || !previews || !actions || !countDisplay) return;

    const MAX_FILES = 6;
    const MAX_SIZE = 12 * 1024 * 1024;
    const ALLOWED_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);
    const dt = new DataTransfer();

    function clearError() {
        form.querySelector('.form-message-error')?.remove();
    }

    function showError(message) {
        clearError();
        const el = document.createElement('p');
        el.className = 'form-message form-message-error';
        el.textContent = message;
        dropZone.before(el);
    }

    function updateUI() {
        const count = dt.files.length;
        previews.hidden = count === 0;
        actions.hidden = count === 0;
        countDisplay.textContent = `${count} of ${MAX_FILES} photo${count === 1 ? '' : 's'} selected`;
        dropZone.classList.toggle('has-photos', count > 0);
    }

    function renderPreviews() {
        previews.innerHTML = '';
        Array.from(dt.files).forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'photo-preview-item';

            const img = document.createElement('img');
            const reader = new FileReader();
            reader.onload = function (e) { img.src = e.target.result; };
            reader.readAsDataURL(file);

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'photo-preview-remove';
            removeButton.setAttribute('aria-label', 'Remove');
            removeButton.setAttribute('data-preview-remove', index);
            removeButton.textContent = '×';

            item.appendChild(img);
            item.appendChild(removeButton);
            previews.appendChild(item);
        });
        updateUI();
    }

    function addFiles(fileList) {
        clearError();
        const errors = [];

        for (const file of fileList) {
            if (!ALLOWED_TYPES.has(file.type)) {
                errors.push(`"${file.name}" is not a supported format.`);
                continue;
            }
            if (file.size > MAX_SIZE) {
                errors.push(`"${file.name}" exceeds 12 MB.`);
                continue;
            }
            if (dt.files.length >= MAX_FILES) {
                errors.push(`Maximum of ${MAX_FILES} photos allowed.`);
                break;
            }
            dt.items.add(file);
        }

        if (errors.length) {
            showError(errors.join(' '));
        }

        fileInput.files = dt.files;
        renderPreviews();
    }

    function reset() {
        clearError();
        while (dt.items.length) dt.items.remove(0);
        fileInput.value = '';
        fileInput.files = dt.files;
        previews.innerHTML = '';
        updateUI();
    }

    dropZone.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) addFiles(fileInput.files);
    });

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('is-drag-over');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('is-drag-over');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('is-drag-over');
        if (e.dataTransfer?.files.length) addFiles(e.dataTransfer.files);
    });

    previews.addEventListener('click', (e) => {
        const button = e.target.closest('[data-preview-remove]');
        if (!button) return;
        const index = Number(button.dataset.previewRemove);
        dt.items.remove(index);
        fileInput.files = dt.files;
        renderPreviews();
    });

    if (clearButton) {
        clearButton.addEventListener('click', reset);
    }

    form.addEventListener('submit', () => {
        fileInput.files = dt.files;
    });
};

window.CraftCrawlInitBusinessPhotoUpload();

window.CraftCrawlInitPhotoReorder = function (root = document) {
    var grid = root.querySelector('.business-photo-grid');
    if (!grid || grid.dataset.reorderReady === 'true') return;
    grid.dataset.reorderReady = 'true';

    var cards = function () { return Array.from(grid.querySelectorAll('.business-photo-card[data-photo-id]')); };
    var dragCard = null;
    var touchClone = null;
    var touchOffsetX = 0;
    var touchOffsetY = 0;
    var csrfInput = root.querySelector('input[name="csrf_token"]');
    var csrfToken = csrfInput ? csrfInput.value : '';

    function saveOrder() {
        var ids = cards().map(function (c) { return c.dataset.photoId; });
        var form = new FormData();
        form.append('form_action', 'reorder_photos');
        if (csrfToken) form.append('csrf_token', csrfToken);
        ids.forEach(function (id) { form.append('photo_ids[]', id); });

        fetch('', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: form,
            credentials: 'same-origin'
        });
    }

    // Desktop drag and drop
    grid.addEventListener('dragstart', function (e) {
        var card = e.target.closest('.business-photo-card[data-photo-id]');
        if (!card || !e.target.closest('[data-photo-drag-handle]')) { e.preventDefault(); return; }
        dragCard = card;
        card.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    grid.addEventListener('dragover', function (e) {
        e.preventDefault();
        if (!dragCard) return;
        var target = e.target.closest('.business-photo-card[data-photo-id]');
        if (!target || target === dragCard) return;
        var rect = target.getBoundingClientRect();
        var mid = rect.left + rect.width / 2;
        if (e.clientX < mid) {
            grid.insertBefore(dragCard, target);
        } else {
            grid.insertBefore(dragCard, target.nextSibling);
        }
    });

    grid.addEventListener('dragend', function () {
        if (dragCard) {
            dragCard.classList.remove('is-dragging');
            dragCard = null;
            saveOrder();
        }
    });

    // Set draggable on all cards
    cards().forEach(function (card) {
        card.setAttribute('draggable', 'true');
    });

    // Mobile touch reorder
    grid.addEventListener('touchstart', function (e) {
        var handle = e.target.closest('[data-photo-drag-handle]');
        if (!handle) return;
        var card = handle.closest('.business-photo-card[data-photo-id]');
        if (!card) return;

        e.preventDefault();
        dragCard = card;
        var rect = card.getBoundingClientRect();
        var touch = e.touches[0];
        touchOffsetX = touch.clientX - rect.left;
        touchOffsetY = touch.clientY - rect.top;

        touchClone = card.cloneNode(true);
        touchClone.classList.add('is-touch-ghost');
        touchClone.style.cssText = 'position:fixed;z-index:9999;width:' + rect.width + 'px;height:' + rect.height + 'px;left:' + (touch.clientX - touchOffsetX) + 'px;top:' + (touch.clientY - touchOffsetY) + 'px;opacity:0.85;pointer-events:none;';
        document.body.appendChild(touchClone);
        card.classList.add('is-dragging');
    }, { passive: false });

    document.addEventListener('touchmove', function (e) {
        if (!dragCard || !touchClone) return;
        e.preventDefault();
        var touch = e.touches[0];
        touchClone.style.left = (touch.clientX - touchOffsetX) + 'px';
        touchClone.style.top = (touch.clientY - touchOffsetY) + 'px';

        var elem = document.elementFromPoint(touch.clientX, touch.clientY);
        var target = elem ? elem.closest('.business-photo-card[data-photo-id]') : null;
        if (target && target !== dragCard) {
            var rect = target.getBoundingClientRect();
            var mid = rect.left + rect.width / 2;
            if (touch.clientX < mid) {
                grid.insertBefore(dragCard, target);
            } else {
                grid.insertBefore(dragCard, target.nextSibling);
            }
        }
    }, { passive: false });

    document.addEventListener('touchend', function () {
        if (!dragCard) return;
        dragCard.classList.remove('is-dragging');
        if (touchClone) {
            touchClone.remove();
            touchClone = null;
        }
        saveOrder();
        dragCard = null;
    });
};
window.CraftCrawlInitPhotoReorder();
