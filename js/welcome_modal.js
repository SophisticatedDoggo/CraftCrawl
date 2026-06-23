(() => {
    const modal = document.querySelector('[data-welcome-modal]');

    if (!modal) {
        return;
    }

    const steps = Array.from(modal.querySelectorAll('[data-welcome-step]'));
    const progressText = modal.querySelector('[data-welcome-progress-text]');
    const progressDots = Array.from(modal.querySelectorAll('[data-welcome-progress-dot]'));
    const backButton = modal.querySelector('[data-welcome-back]');
    const nextButton = modal.querySelector('[data-welcome-next]');
    const skipButton = modal.querySelector('[data-welcome-skip]');
    const dismissButton = modal.querySelector('[data-welcome-dismiss]');
    const status = modal.querySelector('[data-welcome-status]');
    const dismissUrl = modal.dataset.dismissUrl;
    const actionButtons = [backButton, nextButton, skipButton, dismissButton].filter(Boolean);
    let activeStep = 0;
    let isDismissing = false;

    function clearStatus() {
        if (!status) {
            return;
        }

        status.textContent = '';
        status.hidden = true;
    }

    function setActionsDisabled(disabled) {
        actionButtons.forEach((button) => {
            button.disabled = disabled;
        });
    }

    function renderStep({ focus = false } = {}) {
        steps.forEach((step, index) => {
            step.hidden = index !== activeStep;
        });

        progressDots.forEach((dot, index) => {
            dot.classList.toggle('is-active', index === activeStep);
        });

        if (progressText) {
            progressText.textContent = `Step ${activeStep + 1} of ${steps.length}`;
        }

        if (backButton) {
            backButton.hidden = activeStep === 0;
        }

        const isLastStep = activeStep === steps.length - 1;
        if (nextButton) {
            nextButton.hidden = isLastStep;
        }
        if (dismissButton) {
            dismissButton.hidden = !isLastStep;
        }

        clearStatus();

        if (focus) {
            window.requestAnimationFrame(() => {
                (isLastStep ? dismissButton : nextButton)?.focus();
            });
        }
    }

    async function dismissWelcome(triggerButton) {
        if (isDismissing) {
            return;
        }

        isDismissing = true;
        clearStatus();
        setActionsDisabled(true);

        try {
            const body = new URLSearchParams({
                csrf_token: window.CRAFTCRAWL_CSRF_TOKEN || ''
            });
            const response = await fetch(dismissUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            });

            if (!response.ok) {
                throw new Error('Unable to dismiss welcome tour.');
            }

            modal.classList.add('is-closing');
            document.body.classList.remove('welcome-modal-open');
            window.setTimeout(() => modal.remove(), 180);
        } catch (error) {
            isDismissing = false;
            setActionsDisabled(false);
            if (status) {
                status.textContent = 'We could not save your progress. Please try again.';
                status.hidden = false;
            }
            triggerButton?.focus();
        }
    }

    function visibleFocusableElements() {
        return Array.from(modal.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
            .filter((element) => !element.hidden && !element.closest('[hidden]'));
    }

    nextButton?.addEventListener('click', () => {
        if (activeStep >= steps.length - 1) {
            return;
        }

        activeStep += 1;
        renderStep({ focus: true });
    });

    backButton?.addEventListener('click', () => {
        if (activeStep <= 0) {
            return;
        }

        activeStep -= 1;
        renderStep({ focus: true });
    });

    skipButton?.addEventListener('click', () => dismissWelcome(skipButton));
    dismissButton?.addEventListener('click', () => dismissWelcome(dismissButton));

    modal.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') {
            return;
        }

        const focusable = visibleFocusableElements();
        if (!focusable.length) {
            event.preventDefault();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    document.body.classList.add('welcome-modal-open');
    renderStep();
    window.requestAnimationFrame(() => nextButton?.focus());
})();
