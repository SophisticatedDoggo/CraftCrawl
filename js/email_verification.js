(function () {
    const form = document.querySelector('[data-verification-code-form]');
    const nativeInput = document.querySelector('[data-verification-native-input]');
    const slots = Array.from(document.querySelectorAll('[data-verification-code-slot]'));

    function cleanCode(value) {
        return String(value || '').replace(/\D+/g, '').slice(0, slots.length || 6);
    }

    function renderSlots() {
        if (!nativeInput || slots.length === 0) {
            return;
        }

        const value = cleanCode(nativeInput.value);
        nativeInput.value = value;
        const activeIndex = Math.min(value.length, slots.length - 1);

        slots.forEach((slot, index) => {
            slot.textContent = value[index] || '';
            slot.classList.toggle('is-filled', index < value.length);
            slot.classList.toggle('is-active', index === activeIndex && value.length < slots.length);
        });
    }

    if (form && nativeInput && slots.length > 0) {
        slots.forEach((slot) => {
            slot.addEventListener('click', () => nativeInput.focus());
        });

        nativeInput.addEventListener('input', renderSlots);
        nativeInput.addEventListener('focus', renderSlots);
        nativeInput.addEventListener('blur', () => {
            slots.forEach((slot) => slot.classList.remove('is-active'));
        });

        form.addEventListener('submit', () => {
            nativeInput.value = cleanCode(nativeInput.value);
        });

        renderSlots();
        nativeInput.focus();
    }

    const successCard = document.querySelector('[data-verification-success]');
    if (successCard) {
        const loginUrl = successCard.getAttribute('data-login-url');
        if (loginUrl) {
            window.setTimeout(() => {
                window.location.href = loginUrl;
            }, 3500);
        }
    }
})();
