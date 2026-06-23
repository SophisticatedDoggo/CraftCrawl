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
