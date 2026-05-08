const gallery = document.querySelector('[data-business-gallery]');

if (gallery) {
    const slides = Array.from(gallery.querySelectorAll('.business-gallery-slide'));
    const dots = Array.from(gallery.querySelectorAll('[data-gallery-dot]'));
    const previousButton = gallery.querySelector('[data-gallery-prev]');
    const nextButton = gallery.querySelector('[data-gallery-next]');
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

    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            manuallyShowSlide(Number(dot.dataset.galleryDot));
        });
    });

    gallery.addEventListener('mouseenter', stopAutoplay);
    gallery.addEventListener('mouseleave', startAutoplay);
    gallery.addEventListener('focusin', stopAutoplay);
    gallery.addEventListener('focusout', startAutoplay);

    startAutoplay();
}
