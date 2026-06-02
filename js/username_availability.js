(function () {
    const fields = document.querySelectorAll('[data-username-field]');

    fields.forEach((field) => {
        const helperId = field.getAttribute('aria-describedby');
        const helper = helperId ? document.getElementById(helperId) : null;
        const endpoint = field.dataset.usernameEndpoint || 'username_check.php';
        let timer = 0;
        let requestId = 0;

        function setState(message, state) {
            if (!helper) {
                return;
            }

            helper.textContent = message;
            helper.classList.remove('is-pending', 'is-met', 'is-unmet');
            if (state) {
                helper.classList.add(state);
            }
        }

        function validateNow() {
            const value = field.value.trim();
            const currentRequest = ++requestId;

            if (value === '') {
                field.setCustomValidity('');
                setState('Choose a username.', '');
                return;
            }

            setState('Checking username...', 'is-pending');

            fetch(`${endpoint}?username=${encodeURIComponent(value)}`, { credentials: 'same-origin' })
                .then((response) => response.json())
                .then((data) => {
                    if (currentRequest !== requestId) {
                        return;
                    }

                    if (data.username && data.username !== value) {
                        field.value = data.username;
                    }

                    if (data.available) {
                        field.setCustomValidity('');
                        setState(data.message || 'Username is available.', 'is-met');
                    } else {
                        field.setCustomValidity(data.message || 'That username is not available.');
                        setState(data.message || 'That username is not available.', 'is-unmet');
                    }
                })
                .catch(() => {
                    if (currentRequest !== requestId) {
                        return;
                    }

                    field.setCustomValidity('Username could not be checked.');
                    setState('Username could not be checked. Please try again.', 'is-unmet');
                });
        }

        field.addEventListener('input', () => {
            window.clearTimeout(timer);
            field.setCustomValidity('');
            setState('Username can use letters, numbers, and underscores.', '');
            timer = window.setTimeout(validateNow, 550);
        });

        field.addEventListener('blur', () => {
            window.clearTimeout(timer);
            validateNow();
        });
    });
})();
