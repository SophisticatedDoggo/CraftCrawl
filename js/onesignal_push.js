(function () {
    const enableButton = document.querySelector('[data-onesignal-enable]');
    const statusMessage = document.querySelector('[data-onesignal-status]');

    function setStatus(message, isError) {
        if (!statusMessage) {
            return;
        }

        statusMessage.hidden = false;
        statusMessage.textContent = message;
        statusMessage.classList.toggle('form-message-error', Boolean(isError));
        statusMessage.classList.toggle('form-message-success', !isError);
    }

    function loadSdk() {
        return new Promise((resolve, reject) => {
            if (window.OneSignalDeferred) {
                resolve();
                return;
            }

            window.OneSignalDeferred = window.OneSignalDeferred || [];
            const script = document.createElement('script');
            script.src = 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js';
            script.defer = true;
            script.onload = resolve;
            script.onerror = () => reject(new Error('OneSignal SDK could not be loaded.'));
            document.head.appendChild(script);
        });
    }

    async function initOneSignal(config) {
        await loadSdk();
        window.OneSignalDeferred = window.OneSignalDeferred || [];

        return new Promise((resolve, reject) => {
            window.OneSignalDeferred.push(async function (OneSignal) {
                try {
                    await OneSignal.init({
                        appId: config.app_id,
                        allowLocalhostAsSecureOrigin: Boolean(config.allow_localhost),
                        path: '/',
                        serviceWorkerPath: 'OneSignalSDKWorker.js',
                        serviceWorkerUpdaterPath: 'OneSignalSDKUpdaterWorker.js',
                        serviceWorkerParam: {
                            scope: '/'
                        }
                    });
                    await OneSignal.login(config.external_id);
                    resolve(OneSignal);
                } catch (error) {
                    reject(error);
                }
            });
        });
    }

    async function requestPermission(OneSignal) {
        if (OneSignal.Notifications && typeof OneSignal.Notifications.requestPermission === 'function') {
            return OneSignal.Notifications.requestPermission();
        }

        if (OneSignal.Slidedown && typeof OneSignal.Slidedown.promptPush === 'function') {
            return OneSignal.Slidedown.promptPush();
        }

        return false;
    }

    fetch('onesignal_config.php', { credentials: 'same-origin' })
        .then((response) => response.ok ? response.json() : null)
        .then((config) => {
            if (!config || !config.enabled || !config.app_id) {
                if (enableButton) {
                    enableButton.disabled = true;
                    setStatus('Push notifications are not configured yet.', true);
                }
                return null;
            }

            return initOneSignal(config);
        })
        .then((OneSignal) => {
            if (!OneSignal || !enableButton) {
                return;
            }

            enableButton.disabled = false;
            enableButton.addEventListener('click', async () => {
                enableButton.disabled = true;
                setStatus('Opening notification permission prompt...', false);

                try {
                    const accepted = await requestPermission(OneSignal);
                    setStatus(accepted ? 'Push notifications are enabled for this browser.' : 'Notifications were not enabled.', !accepted);
                } catch (error) {
                    setStatus('Notifications could not be enabled in this browser.', true);
                } finally {
                    enableButton.disabled = false;
                }
            });
        })
        .catch((error) => {
            console.error('CraftCrawl OneSignal initialization failed:', error);
            if (enableButton) {
                enableButton.disabled = true;
                setStatus(error && error.message ? error.message : 'Push notifications could not be initialized.', true);
            }
        });
})();
