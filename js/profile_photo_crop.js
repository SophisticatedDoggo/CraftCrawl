window.CraftCrawlInitProfilePhotoCrop = function (root = document) {
    const form = root.querySelector('[data-profile-photo-form]');
    if (!form) {
        return;
    }

    if (form.dataset.shellReady === 'true') {
        return;
    }
    form.dataset.shellReady = 'true';

    const input = form.querySelector('[data-profile-photo-input]');
    const dataInput = form.querySelector('[data-profile-photo-cropped-data]');
    const removeInput = form.querySelector('[data-remove-profile-photo]');
    const removeButton = form.querySelector('[data-profile-photo-remove]');
    const preview = form.querySelector('.profile-photo-preview');
    let image = null;
    let imageUrl = '';
    let scale = 1;
    let offsetX = 0;
    let offsetY = 0;
    let dragging = false;
    let dragStart = null;

    document.querySelectorAll('.profile-photo-cropper').forEach((existingModal) => existingModal.remove());

    const modal = document.createElement('div');
    modal.className = 'profile-photo-cropper';
    modal.hidden = true;
    modal.innerHTML = `
        <div class="profile-photo-cropper-backdrop" data-crop-cancel></div>
        <div class="profile-photo-cropper-panel" role="dialog" aria-modal="true" aria-labelledby="profile-photo-cropper-title">
            <h3 id="profile-photo-cropper-title">Adjust Profile Picture</h3>
            <div class="profile-photo-crop-stage" data-crop-stage>
                <img alt="" data-crop-image>
                <span aria-hidden="true"></span>
            </div>
            <label class="profile-photo-zoom">
                <span>Zoom</span>
                <input type="range" min="1" max="3" step="0.01" value="1" data-crop-zoom>
            </label>
            <div class="profile-photo-crop-actions">
                <button type="button" class="button-link-secondary" data-crop-cancel>Cancel</button>
                <button type="button" data-crop-apply>Apply Photo</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    const stage = modal.querySelector('[data-crop-stage]');
    const cropImage = modal.querySelector('[data-crop-image]');
    const zoom = modal.querySelector('[data-crop-zoom]');

    function render() {
        if (!image) {
            return;
        }

        const stageRect = stage.getBoundingClientRect();
        const baseScale = Math.max(stageRect.width / image.naturalWidth, stageRect.height / image.naturalHeight);
        const width = image.naturalWidth * baseScale * scale;
        const height = image.naturalHeight * baseScale * scale;
        const maxOffsetX = Math.max(0, (width - stageRect.width) / 2);
        const maxOffsetY = Math.max(0, (height - stageRect.height) / 2);

        offsetX = Math.min(maxOffsetX, Math.max(-maxOffsetX, offsetX));
        offsetY = Math.min(maxOffsetY, Math.max(-maxOffsetY, offsetY));

        cropImage.style.width = `${width}px`;
        cropImage.style.height = `${height}px`;
        cropImage.style.transform = `translate(${offsetX}px, ${offsetY}px)`;
    }

    function openCropper(file) {
        if (imageUrl) {
            URL.revokeObjectURL(imageUrl);
        }

        imageUrl = URL.createObjectURL(file);
        image = new Image();
        image.onload = function () {
            cropImage.src = imageUrl;
            scale = 1;
            offsetX = 0;
            offsetY = 0;
            zoom.value = '1';
            modal.hidden = false;
            document.body.classList.add('profile-photo-cropper-open');
            render();
        };
        image.src = imageUrl;
    }

    function closeCropper() {
        modal.hidden = true;
        document.body.classList.remove('profile-photo-cropper-open');
    }

    function applyCrop() {
        if (!image) {
            return;
        }

        const outputSize = 800;
        const stageRect = stage.getBoundingClientRect();
        const canvas = document.createElement('canvas');
        canvas.width = outputSize;
        canvas.height = outputSize;
        const ctx = canvas.getContext('2d');
        const baseScale = Math.max(stageRect.width / image.naturalWidth, stageRect.height / image.naturalHeight);
        const drawScale = (outputSize / stageRect.width) * baseScale * scale;
        const drawWidth = image.naturalWidth * drawScale;
        const drawHeight = image.naturalHeight * drawScale;
        const drawX = (outputSize - drawWidth) / 2 + offsetX * (outputSize / stageRect.width);
        const drawY = (outputSize - drawHeight) / 2 + offsetY * (outputSize / stageRect.height);

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, outputSize, outputSize);
        ctx.drawImage(image, drawX, drawY, drawWidth, drawHeight);

        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
        dataInput.value = dataUrl;
        if (removeInput) {
            removeInput.value = '';
        }
        if (preview) {
            preview.innerHTML = `<img src="${dataUrl}" alt="Profile photo preview">`;
        }
        closeCropper();
    }

    input?.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (file) {
            openCropper(file);
        }
    });

    zoom.addEventListener('input', () => {
        scale = Number(zoom.value || 1);
        render();
    });

    stage.addEventListener('pointerdown', (event) => {
        dragging = true;
        dragStart = {
            x: event.clientX,
            y: event.clientY,
            offsetX,
            offsetY
        };
        stage.setPointerCapture(event.pointerId);
    });

    stage.addEventListener('pointermove', (event) => {
        if (!dragging || !dragStart) {
            return;
        }

        offsetX = dragStart.offsetX + event.clientX - dragStart.x;
        offsetY = dragStart.offsetY + event.clientY - dragStart.y;
        render();
    });

    stage.addEventListener('pointerup', () => {
        dragging = false;
        dragStart = null;
    });

    modal.querySelectorAll('[data-crop-cancel]').forEach((button) => {
        button.addEventListener('click', closeCropper);
    });
    modal.querySelector('[data-crop-apply]')?.addEventListener('click', applyCrop);

    removeButton?.addEventListener('click', () => {
        if (dataInput) {
            dataInput.value = '';
        }
        if (removeInput) {
            removeInput.value = '1';
        }
        if (input) {
            input.value = '';
        }
        if (preview) {
            preview.innerHTML = '<span>CC</span>';
        }
    });
};
window.CraftCrawlInitProfilePhotoCrop();
