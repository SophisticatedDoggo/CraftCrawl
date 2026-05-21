window.CraftCrawlInitReviewPhotos = function (root = document) {
    const reviewPhotoButtons = Array.from(root.querySelectorAll('.review-photo-button'));
    const lightbox = root.querySelector('#review-photo-lightbox');

    if (!reviewPhotoButtons.length || !lightbox || lightbox.dataset.reviewPhotosReady === 'true') {
        return false;
    }
    lightbox.dataset.reviewPhotosReady = 'true';

    const lightboxImage = root.querySelector('#review-photo-lightbox-image');
    const lightboxCount = root.querySelector('#review-photo-lightbox-count');
    const previousButton = root.querySelector('#review-photo-lightbox-prev');
    const nextButton = root.querySelector('#review-photo-lightbox-next');
    const closeControls = root.querySelectorAll('[data-lightbox-close]');
    let activePhotoIndex = 0;

    function showReviewPhoto(index) {
        if (!lightboxImage || !lightboxCount) {
            return;
        }

        activePhotoIndex = (index + reviewPhotoButtons.length) % reviewPhotoButtons.length;
        const photoButton = reviewPhotoButtons[activePhotoIndex];

        lightboxImage.src = photoButton.dataset.reviewPhotoUrl;
        lightboxCount.textContent = `${activePhotoIndex + 1} / ${reviewPhotoButtons.length}`;
    }

    function openReviewPhotoLightbox(index) {
        showReviewPhoto(index);
        lightbox.hidden = false;
        document.body.classList.add('lightbox-open');
    }

    function closeReviewPhotoLightbox() {
        if (!lightboxImage) {
            return;
        }

        lightbox.hidden = true;
        lightboxImage.removeAttribute('src');
        document.body.classList.remove('lightbox-open');
    }

    reviewPhotoButtons.forEach((button, index) => {
        button.addEventListener('click', () => {
            openReviewPhotoLightbox(index);
        });
    });

    previousButton?.addEventListener('click', () => {
        showReviewPhoto(activePhotoIndex - 1);
    });

    nextButton?.addEventListener('click', () => {
        showReviewPhoto(activePhotoIndex + 1);
    });

    closeControls.forEach((control) => {
        control.addEventListener('click', closeReviewPhotoLightbox);
    });

    document.addEventListener('keydown', (event) => {
        if (lightbox.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            closeReviewPhotoLightbox();
        }

        if (event.key === 'ArrowLeft') {
            showReviewPhoto(activePhotoIndex - 1);
        }

        if (event.key === 'ArrowRight') {
            showReviewPhoto(activePhotoIndex + 1);
        }
    });

    return true;
};

window.CraftCrawlInitReviewPhotos();

window.CraftCrawlInitReviewPhotoUploads = function (root = document) {
    const forms = Array.from(root.querySelectorAll('[data-review-form]'));

    if (!forms.length) {
        return false;
    }

    const MAX_PHOTOS = 3;
    const MAX_DIMENSION = 1600;
    const TARGET_BYTES = 1800000;
    const MIN_QUALITY = 0.62;

    function showStatus(status, message, isError = false) {
        if (!status) {
            return;
        }

        status.hidden = message === '';
        status.textContent = message;
        status.classList.toggle('form-message-error', isError);
        status.classList.toggle('form-message-success', !isError && message !== '');
    }

    function loadImage(file) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const image = new Image();

            image.onload = () => {
                URL.revokeObjectURL(url);
                resolve(image);
            };
            image.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error('Photo could not be read.'));
            };
            image.src = url;
        });
    }

    function canvasToBlob(canvas, quality) {
        return new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (blob) {
                    resolve(blob);
                    return;
                }

                reject(new Error('Photo could not be compressed.'));
            }, 'image/jpeg', quality);
        });
    }

    async function resizePhoto(file) {
        if (!file.type.startsWith('image/')) {
            return file;
        }

        const image = await loadImage(file);
        const ratio = Math.min(1, MAX_DIMENSION / Math.max(image.naturalWidth || image.width, image.naturalHeight || image.height));

        if (ratio >= 1 && file.size <= TARGET_BYTES) {
            return file;
        }

        const width = Math.max(1, Math.round((image.naturalWidth || image.width) * ratio));
        const height = Math.max(1, Math.round((image.naturalHeight || image.height) * ratio));
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d', { alpha: false });

        canvas.width = width;
        canvas.height = height;
        context.fillStyle = '#fff';
        context.fillRect(0, 0, width, height);
        context.drawImage(image, 0, 0, width, height);

        let quality = 0.82;
        let blob = await canvasToBlob(canvas, quality);

        while (blob.size > TARGET_BYTES && quality > MIN_QUALITY) {
            quality = Math.max(MIN_QUALITY, quality - 0.08);
            blob = await canvasToBlob(canvas, quality);
        }

        if (blob.size >= file.size) {
            return file;
        }

        const baseName = (file.name || 'review-photo').replace(/\.[^.]+$/, '');
        return new File([blob], `${baseName}.jpg`, {
            type: 'image/jpeg',
            lastModified: Date.now()
        });
    }

    async function preparePhotos(input, status, submitButton) {
        const files = Array.from(input.files || []);

        if (!files.length) {
            showStatus(status, '');
            return;
        }

        if (files.length > MAX_PHOTOS) {
            input.value = '';
            showStatus(status, `Choose no more than ${MAX_PHOTOS} photos.`, true);
            return;
        }

        if (typeof DataTransfer === 'undefined') {
            showStatus(status, 'Large phone photos may fail to upload. Choose smaller photos if this does not post.', true);
            return;
        }

        input.dataset.processingPhotos = 'true';
        if (submitButton) {
            submitButton.disabled = true;
        }
        showStatus(status, 'Preparing photos for upload...', false);

        try {
            const preparedFiles = [];
            for (const file of files) {
                preparedFiles.push(await resizePhoto(file));
            }

            const transfer = new DataTransfer();
            preparedFiles.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;

            const savedBytes = files.reduce((total, file) => total + file.size, 0)
                - preparedFiles.reduce((total, file) => total + file.size, 0);
            showStatus(
                status,
                savedBytes > 250000 ? 'Photos are ready for upload.' : '',
                false
            );
        } catch (error) {
            input.value = '';
            showStatus(status, 'That photo could not be prepared. Try choosing a smaller JPEG, PNG, or WebP image.', true);
        } finally {
            delete input.dataset.processingPhotos;
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    }

    forms.forEach((form) => {
        if (form.dataset.reviewPhotoUploadReady === 'true') {
            return;
        }
        form.dataset.reviewPhotoUploadReady = 'true';

        const input = form.querySelector('[data-review-photo-input]');
        const status = form.querySelector('[data-review-photo-status]');
        const submitButton = form.querySelector('button[type="submit"]');

        if (!input) {
            return;
        }

        input.addEventListener('change', () => {
            preparePhotos(input, status, submitButton);
        });

        form.addEventListener('submit', (event) => {
            if (input.dataset.processingPhotos === 'true') {
                event.preventDefault();
                showStatus(status, 'Photos are still being prepared. Try posting again in a moment.', true);
            }
        });
    });

    return true;
};

window.CraftCrawlInitReviewPhotoUploads();
