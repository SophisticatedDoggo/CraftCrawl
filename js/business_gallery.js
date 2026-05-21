window.CraftCrawlInitBusinessGallery = function (root = document) {
    const gallery = root.querySelector('[data-business-gallery]');

    if (!gallery || gallery.dataset.businessGalleryReady === 'true') {
        return false;
    }
    gallery.dataset.businessGalleryReady = 'true';
    const slides = Array.from(gallery.querySelectorAll('.business-gallery-slide'));
    const slideButtons = Array.from(gallery.querySelectorAll('[data-gallery-photo-url]'));
    const dots = Array.from(gallery.querySelectorAll('[data-gallery-dot]'));
    const track = gallery.querySelector('.business-gallery-track');
    const previousButton = gallery.querySelector('[data-gallery-prev]');
    const nextButton = gallery.querySelector('[data-gallery-next]');
    const lightbox = root.querySelector('#business-gallery-lightbox');
    const lightboxImage = root.querySelector('#business-gallery-lightbox-image');
    const lightboxCount = root.querySelector('#business-gallery-lightbox-count');
    const lightboxPreviousButton = root.querySelector('#business-gallery-lightbox-prev');
    const lightboxNextButton = root.querySelector('#business-gallery-lightbox-next');
    const lightboxCloseControls = root.querySelectorAll('[data-gallery-lightbox-close]');
    let lightboxTrack = null;
    let lightboxPreviousImage = null;
    let lightboxNextImage = null;
    let activeIndex = 0;
    let autoplayId = null;
    let lightboxTransitionId = 0;
    let suppressSlideClick = false;
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

    function getLightboxPhotoUrl(index) {
        if (!slideButtons.length) {
            return '';
        }

        return slideButtons[normalizedIndex(index, slideButtons.length)]?.dataset.galleryPhotoUrl || '';
    }

    function setupLightboxTrack() {
        if (!lightbox || !lightboxImage || lightboxTrack) {
            return;
        }

        lightboxTrack = document.createElement('div');
        lightboxTrack.className = 'business-gallery-lightbox-track';
        lightboxImage.before(lightboxTrack);
        lightboxTrack.appendChild(lightboxImage);

        lightboxPreviousImage = lightboxImage.cloneNode(false);
        lightboxNextImage = lightboxImage.cloneNode(false);
        lightboxPreviousImage.removeAttribute('id');
        lightboxNextImage.removeAttribute('id');
        lightboxPreviousImage.setAttribute('aria-hidden', 'true');
        lightboxNextImage.setAttribute('aria-hidden', 'true');
        lightboxPreviousImage.classList.add('business-gallery-lightbox-adjacent-image');
        lightboxNextImage.classList.add('business-gallery-lightbox-adjacent-image');
        lightboxTrack.prepend(lightboxPreviousImage);
        lightboxTrack.appendChild(lightboxNextImage);
    }

    function syncLightboxAdjacentPhotos(index = activeIndex) {
        if (!lightboxPreviousImage || !lightboxNextImage) {
            return;
        }

        lightboxPreviousImage.src = getLightboxPhotoUrl(index - 1);
        lightboxNextImage.src = getLightboxPhotoUrl(index + 1);
    }

    function showSlide(index) {
        if (!slides.length) {
            return;
        }

        activeIndex = normalizedIndex(index);

        slides.forEach((slide, slideIndex) => {
            slide.classList.toggle('is-active', slideIndex === activeIndex);
            slide.style.transform = '';
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

    async function showLightboxPhoto(index, animate = true) {
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
        syncLightboxAdjacentPhotos(activeIndex);

        if (animate && direction !== 'none') {
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
        lightboxPreviousImage?.removeAttribute('src');
        lightboxNextImage?.removeAttribute('src');
        lightboxImage.classList.remove('is-changing-next', 'is-changing-previous');
        document.body.classList.remove('lightbox-open');
        startAutoplay();
    }

    slideButtons.forEach((slideButton, index) => {
        slideButton.addEventListener('click', (event) => {
            if (suppressSlideClick) {
                event.preventDefault();
                suppressSlideClick = false;
                return;
            }

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

    function addDragFollowCarousel(element) {
        if (!element || slides.length < 2) {
            return;
        }

        let startX = null;
        let startY = null;
        let isDragging = false;
        let dragAxisLocked = false;

        function positionSlides(offsetPx = 0, animate = true) {
            element.classList.toggle('is-dragging', !animate);

            slides.forEach((slide, slideIndex) => {
                let relativeIndex = slideIndex - activeIndex;

                if (relativeIndex > slides.length / 2) {
                    relativeIndex -= slides.length;
                } else if (relativeIndex < -slides.length / 2) {
                    relativeIndex += slides.length;
                }

                slide.style.transform = `translateX(calc(${relativeIndex * 100}% + ${offsetPx}px))`;
            });
        }

        function finishDrag(offsetPx, velocityDirection = 0) {
            const width = element.clientWidth || 1;
            const threshold = Math.min(width * 0.2, 90);
            const shouldAdvance = Math.abs(offsetPx) > threshold || velocityDirection !== 0;
            let nextIndex = activeIndex;

            if (shouldAdvance && offsetPx < 0) {
                nextIndex = activeIndex + 1;
            } else if (shouldAdvance && offsetPx > 0) {
                nextIndex = activeIndex - 1;
            }

            showSlide(nextIndex);
            positionSlides(0, true);
            startAutoplay();
        }

        element.addEventListener('touchstart', (event) => {
            const touch = event.touches[0];
            if (!touch) {
                return;
            }

            stopAutoplay();
            startX = touch.clientX;
            startY = touch.clientY;
            isDragging = false;
            dragAxisLocked = false;
            positionSlides(0, false);
        }, { passive: true });

        element.addEventListener('touchmove', (event) => {
            const touch = event.touches[0];
            if (!touch || startX === null || startY === null) {
                return;
            }

            const deltaX = touch.clientX - startX;
            const deltaY = touch.clientY - startY;

            if (!dragAxisLocked) {
                if (Math.abs(deltaX) < 8 && Math.abs(deltaY) < 8) {
                    return;
                }

                dragAxisLocked = true;
                isDragging = Math.abs(deltaX) > Math.abs(deltaY);
            }

            if (!isDragging) {
                return;
            }

            event.preventDefault();
            positionSlides(deltaX, false);
        }, { passive: false });

        element.addEventListener('touchend', (event) => {
            const touch = event.changedTouches[0];
            if (!touch || startX === null || startY === null) {
                return;
            }

            const deltaX = touch.clientX - startX;
            startX = null;
            startY = null;

            if (!isDragging) {
                element.classList.remove('is-dragging');
                startAutoplay();
                return;
            }

            suppressSlideClick = Math.abs(deltaX) > 8;
            finishDrag(deltaX);
            isDragging = false;
            dragAxisLocked = false;
        }, { passive: true });

        element.addEventListener('touchcancel', () => {
            startX = null;
            startY = null;
            isDragging = false;
            dragAxisLocked = false;
            positionSlides(0, true);
            startAutoplay();
        }, { passive: true });

        positionSlides(0, true);
    }

    addDragFollowCarousel(track);

    function addDragFollowLightbox(element) {
        if (!element || !lightboxTrack || !lightboxImage || slideButtons.length < 2) {
            return;
        }

        let startX = null;
        let startY = null;
        let isDragging = false;
        let dragAxisLocked = false;

        function positionImages(offsetPx = 0, animate = true) {
            lightboxTrack.classList.toggle('is-dragging', !animate);

            if (lightboxPreviousImage) {
                lightboxPreviousImage.style.transform = `translateX(calc(-100vw + ${offsetPx}px))`;
            }

            lightboxImage.style.transform = `translateX(${offsetPx}px)`;

            if (lightboxNextImage) {
                lightboxNextImage.style.transform = `translateX(calc(100vw + ${offsetPx}px))`;
            }
        }

        function clearImagePositions() {
            positionImages(0, true);
        }

        function finishDrag(offsetPx) {
            const width = element.clientWidth || window.innerWidth || 1;
            const threshold = Math.min(width * 0.2, 90);

            if (Math.abs(offsetPx) > threshold) {
                const nextIndex = activeIndex + (offsetPx < 0 ? 1 : -1);
                positionImages(offsetPx < 0 ? -width : width, true);
                window.setTimeout(() => {
                    showLightboxPhoto(nextIndex, false).then(() => {
                        positionImages(0, false);
                        window.requestAnimationFrame(clearImagePositions);
                    });
                }, 320);
                return;
            }

            positionImages(0, true);
            window.setTimeout(clearImagePositions, 320);
        }

        element.addEventListener('touchstart', (event) => {
            const touch = event.touches[0];
            if (!touch) {
                return;
            }

            lightboxImage.classList.remove('is-changing-next', 'is-changing-previous');
            syncLightboxAdjacentPhotos(activeIndex);
            startX = touch.clientX;
            startY = touch.clientY;
            isDragging = false;
            dragAxisLocked = false;
            positionImages(0, false);
        }, { passive: true });

        element.addEventListener('touchmove', (event) => {
            const touch = event.touches[0];
            if (!touch || startX === null || startY === null) {
                return;
            }

            const deltaX = touch.clientX - startX;
            const deltaY = touch.clientY - startY;

            if (!dragAxisLocked) {
                if (Math.abs(deltaX) < 8 && Math.abs(deltaY) < 8) {
                    return;
                }

                dragAxisLocked = true;
                isDragging = Math.abs(deltaX) > Math.abs(deltaY);
            }

            event.preventDefault();

            if (!isDragging) {
                return;
            }

            positionImages(deltaX, false);
        }, { passive: false });

        element.addEventListener('touchend', (event) => {
            const touch = event.changedTouches[0];
            if (!touch || startX === null || startY === null) {
                return;
            }

            const deltaX = touch.clientX - startX;
            startX = null;
            startY = null;

            if (!isDragging) {
                clearImagePositions();
                return;
            }

            finishDrag(deltaX);
            isDragging = false;
            dragAxisLocked = false;
        }, { passive: true });

        element.addEventListener('touchcancel', () => {
            startX = null;
            startY = null;
            isDragging = false;
            dragAxisLocked = false;
            positionImages(0, true);
            window.setTimeout(clearImagePositions, 320);
        }, { passive: true });

        positionImages(0, true);
    }

    setupLightboxTrack();
    addDragFollowLightbox(lightbox);

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
    return true;
};

window.CraftCrawlInitBusinessGallery();
