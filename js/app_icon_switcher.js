function getNativeAppIconPlugin() {
    return typeof window.getCraftCrawlNativePlugin === 'function' ? window.getCraftCrawlNativePlugin('CraftCrawlAppIcon') : null;
}
window.CraftCrawlInitAppIconSwitcher = function (root = document) {
    const settings = root.querySelector('[data-native-app-icon-settings]');
    const buttons = root.querySelectorAll('[data-app-icon-option]');
    const status = root.querySelector('[data-app-icon-status]');
    if (!settings || !buttons.length || settings.dataset.appIconReady === 'true') return;
    const appIcon = getNativeAppIconPlugin();
    if (!appIcon || typeof appIcon.getCurrentIcon !== 'function' || typeof appIcon.setIcon !== 'function') return;
    settings.dataset.appIconReady = 'true';
    const showStatus = (message, isError) => {
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('form-message-error', isError);
        status.classList.toggle('form-message-success', !isError);
        status.hidden = false;
    };
    const setActive = (iconName) => buttons.forEach((button) => {
        const active = button.dataset.appIconOption === iconName;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', String(active));
    });
    const setDisabled = (disabled) => buttons.forEach((button) => {
        button.disabled = disabled || button.dataset.appIconLocked === 'true';
    });
    appIcon.getCurrentIcon().then((data) => {
        const iconName = data.name || 'trail';
        settings.hidden = false;
        setActive(iconName);
        window.syncCraftCrawlNativeSplashPreference?.(iconName);
    }).catch(() => {});
    buttons.forEach((button) => button.addEventListener('click', () => {
        const iconName = button.dataset.appIconOption || 'trail';
        setDisabled(true); showStatus('Updating app icon...', false);
        appIcon.setIcon({ name: iconName })
            .then((data) => {
                const updatedIconName = data.name || iconName;
                setActive(updatedIconName);
                window.syncCraftCrawlNativeSplashPreference?.(updatedIconName);
                showStatus('App icon and splash updated.', false);
            })
            .catch((error) => showStatus(error?.message || 'App icon could not be updated.', true))
            .finally(() => setDisabled(false));
    }));
};
window.CraftCrawlInitAppIconSwitcher();
