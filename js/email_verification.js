(function () {
    const form = document.querySelector('[data-verification-code-form]');
    const hiddenInput = document.querySelector('[data-verification-native-input]');
    const inputs = Array.from(document.querySelectorAll('[data-verification-code-input]'));
    const codeLength = inputs.length || 6;

    function cleanCode(value) {
        return String(value || '').replace(/\D+/g, '').slice(0, codeLength);
    }

    function joinedCode() {
        return cleanCode(inputs.map((input) => input.value).join(''));
    }

    function syncHiddenInput() {
        if (hiddenInput) {
            hiddenInput.value = joinedCode();
        }
    }

    function setCode(value, focusIndex) {
        const code = cleanCode(value);

        inputs.forEach((input, index) => {
            input.value = code[index] || '';
        });

        syncHiddenInput();

        const nextIndex = Math.min(
            typeof focusIndex === 'number' ? focusIndex : code.length,
            inputs.length - 1
        );

        if (inputs[nextIndex]) {
            inputs[nextIndex].focus();
            inputs[nextIndex].select();
        }
    }

    function setActiveInput(activeInput) {
        inputs.forEach((input) => {
            input.classList.toggle('is-active', input === activeInput);
            input.classList.toggle('is-filled', input.value !== '');
        });
    }

    if (form && hiddenInput && inputs.length > 0) {
        setCode(hiddenInput.value || inputs[0].value || '', 0);

        inputs.forEach((input, index) => {
            input.addEventListener('focus', () => {
                input.select();
                setActiveInput(input);
            });

            input.addEventListener('input', () => {
                const value = cleanCode(input.value);

                if (value.length > 1) {
                    const before = inputs.slice(0, index).map((slot) => slot.value).join('');
                    setCode(before + value, index + value.length);
                    return;
                }

                input.value = value;
                syncHiddenInput();
                setActiveInput(input);

                if (value && inputs[index + 1]) {
                    inputs[index + 1].focus();
                    inputs[index + 1].select();
                }
            });

            input.addEventListener('paste', (event) => {
                const pasted = cleanCode(event.clipboardData?.getData('text') || '');

                if (!pasted) {
                    return;
                }

                event.preventDefault();
                const before = inputs.slice(0, index).map((slot) => slot.value).join('');
                setCode(before + pasted, index + pasted.length);
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace' && input.value === '' && inputs[index - 1]) {
                    event.preventDefault();
                    inputs[index - 1].value = '';
                    syncHiddenInput();
                    inputs[index - 1].focus();
                    inputs[index - 1].select();
                    return;
                }

                if (event.key === 'ArrowLeft' && inputs[index - 1]) {
                    event.preventDefault();
                    inputs[index - 1].focus();
                    inputs[index - 1].select();
                    return;
                }

                if (event.key === 'ArrowRight' && inputs[index + 1]) {
                    event.preventDefault();
                    inputs[index + 1].focus();
                    inputs[index + 1].select();
                }
            });

            input.addEventListener('blur', () => {
                input.classList.remove('is-active');
            });
        });

        form.addEventListener('submit', () => {
            syncHiddenInput();
        });

        inputs[0].focus();
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
