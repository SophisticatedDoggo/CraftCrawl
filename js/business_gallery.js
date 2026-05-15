const gallery = document.querySelector('[data-business-gallery]');

if (gallery) {
    const slides = Array.from(gallery.querySelectorAll('.business-gallery-slide'));
    const slideButtons = Array.from(gallery.querySelectorAll('[data-gallery-photo-url]'));
    const dots = Array.from(gallery.querySelectorAll('[data-gallery-dot]'));
    const track = gallery.querySelector('.business-gallery-track');
    const lightbox = document.getElementById('business-gallery-lightbox');
    const lightboxImage = document.getElementById('business-gallery-lightbox-image');
    const lightboxCount = document.getElementById('business-gallery-lightbox-count');
    const lightboxCloseControls = document.querySelectorAll('[data-gallery-lightbox-close]');
    let activeIndex = 0;
    let autoplayId = null;

    function showSlide(index) {
        if (!slides.length) {
            return;
        }

        activeIndex = (index + slides.length) % slides.length;

        slides.forEach((slide, slideIndex) => {
            slide.classList.toggle('is-active', slideIndex === activeIndex);
        });

        dots.forEach((dot, dotIndex) => {
            dot.classList.toggle('is-active', dotIndex === activeIndex);
        });
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

    function showLightboxPhoto(index) {
        if (!slideButtons.length || !lightboxImage || !lightboxCount) {
            return;
        }

        activeIndex = (index + slideButtons.length) % slideButtons.length;
        const slideButton = slideButtons[activeIndex];

        showSlide(activeIndex);
        lightboxImage.src = slideButton.dataset.galleryPhotoUrl;
        lightboxCount.textContent = `${activeIndex + 1} / ${slideButtons.length}`;
    }

    function openLightbox(index) {
        if (!lightbox) {
            return;
        }

        stopAutoplay();
        showLightboxPhoto(index);
        lightbox.hidden = false;
        document.body.classList.add('lightbox-open');
    }

    function closeLightbox() {
        if (!lightbox || !lightboxImage) {
            return;
        }

        lightbox.hidden = true;
        lightboxImage.removeAttribute('src');
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

    startAutoplay();
}
