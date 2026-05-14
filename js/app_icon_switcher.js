const appIconSettings = document.querySelector('[data-native-app-icon-settings]');
const appIconButtons = document.querySelectorAll('[data-app-icon-option]');
const appIconStatus = document.querySelector('[data-app-icon-status]');

function showAppIconStatus(message, isError) {
    if (!appIconStatus) {
        return;
    }

    appIconStatus.textContent = message;
    appIconStatus.classList.toggle('form-message-error', isError);
    appIconStatus.classList.toggle('form-message-success', !isError);
    appIconStatus.hidden = false;
}

function setActiveAppIconButton(iconName) {
    appIconButtons.forEach((button) => {
        const isActive = button.dataset.appIconOption === iconName;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', String(isActive));
    });
}

function setAppIconButtonsDisabled(disabled) {
    appIconButtons.forEach((button) => {
        button.disabled = disabled;
    });
}

function getNativeAppIconPlugin() {
    if (typeof window.getCraftCrawlNativePlugin !== 'function') {
        return null;
    }

    return window.getCraftCrawlNativePlugin('CraftCrawlAppIcon');
}

function initAppIconSwitcher() {
    if (!appIconSettings || appIconButtons.length === 0) {
        return;
    }

    const appIcon = getNativeAppIconPlugin();
    if (!appIcon || typeof appIcon.getCurrentIcon !== 'function' || typeof appIcon.setIcon !== 'function') {
        return;
    }

    appIcon.getCurrentIcon()
        .then((data) => {
            appIconSettings.hidden = false;
            setActiveAppIconButton(data.name || 'trail');
        })
        .catch(() => {});

    appIconButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const iconName = button.dataset.appIconOption || 'trail';

            setAppIconButtonsDisabled(true);
            showAppIconStatus('Updating app icon...', false);

            appIcon.setIcon({ name: iconName })
                .then((data) => {
                    setActiveAppIconButton(data.name || iconName);
                    showAppIconStatus('App icon updated.', false);
                })
                .catch((error) => {
                    showAppIconStatus(error && error.message ? error.message : 'App icon could not be updated.', true);
                })
                .finally(() => {
                    setAppIconButtonsDisabled(false);
                });
        });
    });
}

initAppIconSwitcher();
