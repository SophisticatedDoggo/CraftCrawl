const gallery = document.querySelector('[data-business-gallery]');

if (gallery) {
    const slides = Array.from(gallery.querySelectorAll('.business-gallery-slide'));
    const slideButtons = Array.from(gallery.querySelectorAll('[data-gallery-photo-url]'));
    const dots = Array.from(gallery.querySelectorAll('[data-gallery-dot]'));
    const track = gallery.querySelector('.business-gallery-track');
    const previousButton = gallery.querySelector('[data-gallery-prev]');
    const nextButton = gallery.querySelector('[data-gallery-next]');
    const lightbox = document.getElementById('business-gallery-lightbox');
    const lightboxImage = document.getElementById('business-gallery-lightbox-image');
    const lightboxCount = document.getElementById('business-gallery-lightbox-count');
    const lightboxPreviousButton = document.getElementById('business-gallery-lightbox-prev');
    const lightboxNextButton = document.getElementById('business-gallery-lightbox-next');
    const lightboxCloseControls = document.querySelectorAll('[data-gallery-lightbox-close]');
    let activeIndex = 0;
    let autoplayId = null;
    let lightboxTransitionId = 0;
    const preloadedImages = new Map();

    function normalizedIndex(index, count = slides.length) {
        return (index + count) % count;
    }

    function preloadImage(url) {
        if (!url) {
            return Promise.resolve();
        }

        if (!preloadedImages.has(url)) {
            preloadedImages.set(url, new Promise((resolve) => {
                const image = new Image();
                image.onload = resolve;
                image.onerror = resolve;
                image.src = url;
            }));
        }

        return preloadedImages.get(url);
    }

    function preloadNearbyPhotos(index) {
        if (!slideButtons.length) {
            return;
        }

        [-1, 0, 1].forEach((offset) => {
            const nearbyIndex = normalizedIndex(index + offset, slideButtons.length);
            const slideButton = slideButtons[nearbyIndex];
            const slideImage = slideButton?.querySelector('img');

            preloadImage(slideImage?.currentSrc || slideImage?.src);
            preloadImage(slideButton?.dataset.galleryPhotoUrl);
        });
    }

    function showSlide(index) {
        if (!slides.length) {
            return;
        }

        activeIndex = normalizedIndex(index);

        slides.forEach((slide, slideIndex) => {
            slide.classList.toggle('is-active', slideIndex === activeIndex);
            slide.classList.toggle('is-before', slideIndex < activeIndex);
        });

        dots.forEach((dot, dotIndex) => {
            dot.classList.toggle('is-active', dotIndex === activeIndex);
        });

        preloadNearbyPhotos(activeIndex);
    }

    function stopAutoplay() {
        if (autoplayId) {
            window.clearInterval(autoplayId);
            autoplayId = null;
        }
    }

    function startAutoplay() {
        stopAutoplay();

        if (slides.length > 1) {
            autoplayId = window.setInterval(() => {
                showSlide(activeIndex + 1);
            }, 8000);
        }
    }

    function manuallyShowSlide(index) {
        showSlide(index);
        startAutoplay();
    }

    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            manuallyShowSlide(Number(dot.dataset.galleryDot));
        });
    });

    if (previousButton) {
        previousButton.addEventListener('click', () => {
            manuallyShowSlide(activeIndex - 1);
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            manuallyShowSlide(activeIndex + 1);
        });
    }

    async function showLightboxPhoto(index) {
        if (!slideButtons.length || !lightboxImage || !lightboxCount) {
            return;
        }

        const nextIndex = normalizedIndex(index, slideButtons.length);
        const previousIndex = activeIndex;
        const slideButton = slideButtons[nextIndex];
        const nextUrl = slideButton.dataset.galleryPhotoUrl;
        const transitionId = ++lightboxTransitionId;
        const direction = nextIndex === previousIndex
            ? 'none'
            : normalizedIndex(previousIndex + 1, slideButtons.length) === nextIndex
                ? 'next'
                : 'previous';

        await preloadImage(nextUrl);

        if (transitionId !== lightboxTransitionId) {
            return;
        }

        activeIndex = nextIndex;
        showSlide(activeIndex);
        lightboxImage.classList.remove('is-changing-next', 'is-changing-previous');
        lightboxImage.src = nextUrl;

        if (direction !== 'none') {
            void lightboxImage.offsetWidth;
            lightboxImage.classList.add(`is-changing-${direction}`);
        }

        lightboxCount.textContent = `${activeIndex + 1} / ${slideButtons.length}`;
    }

    async function openLightbox(index) {
        if (!lightbox) {
            return;
        }

        stopAutoplay();
        await showLightboxPhoto(index);
        lightbox.hidden = false;
        document.body.classList.add('lightbox-open');
    }

    function closeLightbox() {
        if (!lightbox || !lightboxImage) {
            return;
        }

        lightboxTransitionId++;
        lightbox.hidden = true;
        lightboxImage.removeAttribute('src');
        lightboxImage.classList.remove('is-changing-next', 'is-changing-previous');
        document.body.classList.remove('lightbox-open');
        startAutoplay();
    }

    slideButtons.forEach((slideButton, index) => {
        slideButton.addEventListener('click', () => {
            openLightbox(index);
        });
    });

    lightboxCloseControls.forEach((control) => {
        control.addEventListener('click', closeLightbox);
    });

    if (lightboxPreviousButton) {
        lightboxPreviousButton.addEventListener('click', () => {
            showLightboxPhoto(activeIndex - 1);
        });
    }

    if (lightboxNextButton) {
        lightboxNextButton.addEventListener('click', () => {
            showLightboxPhoto(activeIndex + 1);
        });
    }

    function addSwipeNavigation(element, onPrevious, onNext) {
        if (!element || slides.length < 2) {
            return;
        }

        let startX = null;
        let startY = null;

        element.addEventListener('touchstart', (event) => {
            const touch = event.touches[0];
            if (!touch) {
                return;
            }

            startX = touch.clientX;
            startY = touch.clientY;
        }, { passive: true });

        element.addEventListener('touchend', (event) => {
            const touch = event.changedTouches[0];
            if (!touch || startX === null || startY === null) {
                return;
            }

            const deltaX = touch.clientX - startX;
            const deltaY = touch.clientY - startY;
            startX = null;
            startY = null;

            if (Math.abs(deltaX) < 42 || Math.abs(deltaX) <= Math.abs(deltaY)) {
                return;
            }

            if (deltaX > 0) {
                onPrevious();
                return;
            }

            onNext();
        }, { passive: true });

        element.addEventListener('touchcancel', () => {
            startX = null;
            startY = null;
        }, { passive: true });
    }

    addSwipeNavigation(
        track,
        () => manuallyShowSlide(activeIndex - 1),
        () => manuallyShowSlide(activeIndex + 1)
    );

    addSwipeNavigation(
        lightbox,
        () => showLightboxPhoto(activeIndex - 1),
        () => showLightboxPhoto(activeIndex + 1)
    );

    if (lightbox) {
        lightbox.addEventListener('touchmove', (event) => {
            event.preventDefault();
        }, { passive: false });
    }

    document.addEventListener('keydown', (event) => {
        if (!lightbox || lightbox.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            closeLightbox();
        }

        if (event.key === 'ArrowLeft') {
            showLightboxPhoto(activeIndex - 1);
        }

        if (event.key === 'ArrowRight') {
            showLightboxPhoto(activeIndex + 1);
        }
    });

    gallery.addEventListener('mouseenter', stopAutoplay);
    gallery.addEventListener('mouseleave', startAutoplay);
    gallery.addEventListener('focusin', stopAutoplay);
    gallery.addEventListener('focusout', startAutoplay);

    preloadNearbyPhotos(activeIndex);
    window.setTimeout(() => {
        slideButtons.forEach((slideButton) => {
            const slideImage = slideButton.querySelector('img');
            preloadImage(slideImage?.currentSrc || slideImage?.src);
            preloadImage(slideButton.dataset.galleryPhotoUrl);
        });
    }, 250);

    startAutoplay();
}
