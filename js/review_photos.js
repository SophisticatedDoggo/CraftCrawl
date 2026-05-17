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
