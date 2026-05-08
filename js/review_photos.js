const reviewPhotoButtons = Array.from(document.querySelectorAll('.review-photo-button'));
const lightbox = document.getElementById('review-photo-lightbox');
const lightboxImage = document.getElementById('review-photo-lightbox-image');
const lightboxCount = document.getElementById('review-photo-lightbox-count');
const previousButton = document.getElementById('review-photo-lightbox-prev');
const nextButton = document.getElementById('review-photo-lightbox-next');
const closeControls = document.querySelectorAll('[data-lightbox-close]');

let activePhotoIndex = 0;

function showReviewPhoto(index) {
    if (!reviewPhotoButtons.length || !lightbox || !lightboxImage || !lightboxCount) {
        return;
    }

    activePhotoIndex = (index + reviewPhotoButtons.length) % reviewPhotoButtons.length;
    const photoButton = reviewPhotoButtons[activePhotoIndex];

    lightboxImage.src = photoButton.dataset.reviewPhotoUrl;
    lightboxCount.textContent = `${activePhotoIndex + 1} / ${reviewPhotoButtons.length}`;
}

function openReviewPhotoLightbox(index) {
    if (!lightbox) {
        return;
    }

    showReviewPhoto(index);
    lightbox.hidden = false;
    document.body.classList.add('lightbox-open');
}

function closeReviewPhotoLightbox() {
    if (!lightbox || !lightboxImage) {
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

if (previousButton) {
    previousButton.addEventListener('click', () => {
        showReviewPhoto(activePhotoIndex - 1);
    });
}

if (nextButton) {
    nextButton.addEventListener('click', () => {
        showReviewPhoto(activePhotoIndex + 1);
    });
}

closeControls.forEach((control) => {
    control.addEventListener('click', closeReviewPhotoLightbox);
});

document.addEventListener('keydown', (event) => {
    if (!lightbox || lightbox.hidden) {
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
