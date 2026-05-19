function setupPasswordRequirementHelpers() {
    const passwordFields = document.querySelectorAll('[data-password-requirements]');

    passwordFields.forEach((passwordField) => {
        const requirementsId = passwordField.getAttribute('aria-describedby');
        const requirementsList = requirementsId ? document.getElementById(requirementsId) : null;
        const verifyField = document.querySelector('[data-password-match-for="' + passwordField.id + '"]');
        const matchMessageId = verifyField ? verifyField.getAttribute('aria-describedby') : null;
        const matchMessage = matchMessageId ? document.getElementById(matchMessageId) : null;

        if (!requirementsList || passwordField.dataset.requirementsReady === 'true') {
            return;
        }

        const rules = [
            { selector: '[data-password-rule="length"]', test: (value) => value.length >= 10 },
            { selector: '[data-password-rule="uppercase"]', test: (value) => /[A-Z]/.test(value) },
            { selector: '[data-password-rule="lowercase"]', test: (value) => /[a-z]/.test(value) },
            { selector: '[data-password-rule="number"]', test: (value) => /[0-9]/.test(value) },
            { selector: '[data-password-rule="symbol"]', test: (value) => /[!@#$%^&*]/.test(value) }
        ];

        const updateRequirement = (item, met) => {
            item.classList.toggle('is-met', met);
            item.classList.toggle('is-unmet', !met);
            item.setAttribute('aria-label', (met ? 'Met: ' : 'Not met: ') + item.textContent.trim());
        };

        const updateMatchMessage = () => {
            if (!verifyField || !matchMessage) {
                return;
            }

            const hasVerification = verifyField.value.length > 0;
            const matches = hasVerification && verifyField.value === passwordField.value;

            matchMessage.classList.toggle('is-met', matches);
            matchMessage.classList.toggle('is-unmet', hasVerification && !matches);
            matchMessage.textContent = matches ? 'Passwords match.' : 'Passwords must match.';
        };

        const updateRequirements = () => {
            rules.forEach((rule) => {
                const item = requirementsList.querySelector(rule.selector);

                if (item) {
                    updateRequirement(item, rule.test(passwordField.value));
                }
            });

            updateMatchMessage();
        };

        passwordField.dataset.requirementsReady = 'true';
        passwordField.addEventListener('input', updateRequirements);

        if (verifyField) {
            verifyField.addEventListener('input', updateMatchMessage);
        }

        updateRequirements();
    });
}

setupPasswordRequirementHelpers();
