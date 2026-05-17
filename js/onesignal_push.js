(function () {
    const isUserPath = /\/user\/?$|\/user\//.test(window.location.pathname);
    const nativeAutoPromptKey = 'craftcrawl_native_push_auto_prompted_v2';
    const initializedToggles = new WeakSet();
    let oneSignalPromise = null;

    function userEndpoint(file) {
        return isUserPath ? file : `user/${file}`;
    }

    function getToggle(root = document) {
        return root.querySelector?.('[data-onesignal-toggle]') || null;
    }

    function getStatus(root = document) {
        return root.querySelector?.('[data-onesignal-status]') || document.querySelector('[data-onesignal-status]');
    }

    function getNativeOneSignalPlugin() {
        const capacitor = window.Capacitor;
        const isNative = capacitor
            && typeof capacitor.isNativePlatform === 'function'
            && capacitor.isNativePlatform();

        if (!isNative) {
            return null;
        }

        const rawPlugin = capacitor.Plugins?.OneSignalCapacitor
            || (typeof capacitor.registerPlugin === 'function'
                ? capacitor.registerPlugin('OneSignalCapacitor')
                : null);

        if (!rawPlugin) {
            return null;
        }

        if (rawPlugin.Notifications && rawPlugin.User?.pushSubscription) {
            return rawPlugin;
        }

        return {
            initialize(appId) {
                return rawPlugin.initialize({ appId });
            },
            login(externalId) {
                return rawPlugin.login({ externalId });
            },
            Notifications: {
                addEventListener(event, listener) {
                    if (event === 'click' && typeof rawPlugin.addListener === 'function') {
                        rawPlugin.addListener('notificationClick', listener);
                    }
                },
                async hasPermission() {
                    return Boolean((await rawPlugin.getPermission()).permission);
                },
                async canRequestPermission() {
                    return Boolean((await rawPlugin.canRequestPermission()).canRequest);
                },
                async requestPermission(fallbackToSettings) {
                    const response = await rawPlugin.requestPermission({ fallbackToSettings });
                    return Boolean(response.permission ?? response.accepted);
                }
            },
            User: {
                pushSubscription: {
                    async getOptedInAsync() {
                        return Boolean((await rawPlugin.getPushSubscriptionOptedIn()).optedIn);
                    },
                    optIn() {
                        return rawPlugin.optInPushSubscription();
                    },
                    optOut() {
                        return rawPlugin.optOutPushSubscription();
                    }
                }
            }
        };
    }

    function hasAutoPromptedNativePush() {
        try {
            return window.localStorage.getItem(nativeAutoPromptKey) === '1';
        } catch (error) {
            return true;
        }
    }

    function markAutoPromptedNativePush() {
        try {
            window.localStorage.setItem(nativeAutoPromptKey, '1');
        } catch (error) {
            // Ignore storage failures; the OS permission prompt still controls repeat prompts.
        }
    }

    function setStatus(message, isError, root = document) {
        const statusMessage = getStatus(root);
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

    function nativeNotificationPath(event) {
        const path = event?.notification?.additionalData?.craftcrawl_path;

        if (typeof path !== 'string' || !path.startsWith('/') || path.startsWith('//')) {
            return '';
        }

        return path;
    }

    function handleNativeNotificationClick(event) {
        const path = nativeNotificationPath(event);

        if (!path) {
            return;
        }

        if (typeof window.CraftCrawlNavigateUserShell === 'function'
            && window.CraftCrawlNavigateUserShell(path)) {
            return;
        }

        window.location.assign(path);
    }

    async function initNativeOneSignal(config) {
        const OneSignal = getNativeOneSignalPlugin();

        if (!OneSignal) {
            return null;
        }

        await OneSignal.initialize(config.app_id);
        if (typeof OneSignal.Notifications.addClickListener === 'function') {
            OneSignal.Notifications.addClickListener(handleNativeNotificationClick);
        } else {
            OneSignal.Notifications.addEventListener?.('click', handleNativeNotificationClick);
        }
        await OneSignal.login(config.external_id);

        return OneSignal;
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
                        serviceWorkerPath: '/OneSignalSDKWorker.js',
                        serviceWorkerUpdaterPath: '/OneSignalSDKUpdaterWorker.js',
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

    function ensureOneSignal() {
        if (oneSignalPromise) {
            return oneSignalPromise;
        }

        oneSignalPromise = fetch(userEndpoint('onesignal_config.php'), { credentials: 'same-origin' })
            .then((response) => response.ok ? response.json() : null)
            .then((config) => {
                if (!config || !config.enabled || !config.app_id) {
                    return null;
                }

                return getNativeOneSignalPlugin()
                    ? initNativeOneSignal(config)
                    : initOneSignal(config);
            });

        return oneSignalPromise;
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

    async function nativeHasPermission(OneSignal) {
        if (OneSignal.Notifications && typeof OneSignal.Notifications.hasPermission === 'function') {
            return Boolean(await OneSignal.Notifications.hasPermission());
        }

        return false;
    }

    async function nativeCanRequestPermission(OneSignal) {
        if (OneSignal.Notifications && typeof OneSignal.Notifications.canRequestPermission === 'function') {
            return Boolean(await OneSignal.Notifications.canRequestPermission());
        }

        return true;
    }

    async function nativeIsOptedIn(OneSignal) {
        if (OneSignal.User?.pushSubscription && typeof OneSignal.User.pushSubscription.getOptedInAsync === 'function') {
            return Boolean(await OneSignal.User.pushSubscription.getOptedInAsync());
        }

        return nativeHasPermission(OneSignal);
    }

    async function ensureNativePushOptIn(OneSignal) {
        if (OneSignal.User?.pushSubscription && typeof OneSignal.User.pushSubscription.optIn === 'function') {
            await OneSignal.User.pushSubscription.optIn();
        }
    }

    async function optOutNativePush(OneSignal) {
        if (OneSignal.User?.pushSubscription && typeof OneSignal.User.pushSubscription.optOut === 'function') {
            await OneSignal.User.pushSubscription.optOut();
        }
    }

    async function nativePushIsEnabledOnThisDevice(OneSignal) {
        return await nativeHasPermission(OneSignal) && await nativeIsOptedIn(OneSignal);
    }

    function webPushSubscription(OneSignal) {
        return OneSignal.User?.PushSubscription || OneSignal.User?.pushSubscription || null;
    }

    async function webPushIsEnabledInThisBrowser(OneSignal) {
        const subscription = webPushSubscription(OneSignal);

        if (subscription && typeof subscription.optedIn === 'boolean') {
            return subscription.optedIn;
        }

        return window.Notification?.permission === 'granted';
    }

    async function enableWebPushInThisBrowser(OneSignal) {
        const subscription = webPushSubscription(OneSignal);

        if (subscription && typeof subscription.optIn === 'function') {
            await subscription.optIn();
            return webPushIsEnabledInThisBrowser(OneSignal);
        }

        return Boolean(await requestPermission(OneSignal));
    }

    async function disableWebPushInThisBrowser(OneSignal) {
        const subscription = webPushSubscription(OneSignal);

        if (subscription && typeof subscription.optOut === 'function') {
            await subscription.optOut();
            return true;
        }

        return false;
    }

    async function syncToggleState(OneSignal, toggleInput) {
        toggleInput.checked = getNativeOneSignalPlugin()
            ? await nativePushIsEnabledOnThisDevice(OneSignal)
            : await webPushIsEnabledInThisBrowser(OneSignal);
    }

    async function requestNativePermission(OneSignal) {
        if (await nativeHasPermission(OneSignal)) {
            await ensureNativePushOptIn(OneSignal);
            return true;
        }

        if (OneSignal.Notifications && typeof OneSignal.Notifications.requestPermission === 'function') {
            const accepted = Boolean(await OneSignal.Notifications.requestPermission(true));
            if (accepted) {
                await ensureNativePushOptIn(OneSignal);
            }
            return accepted;
        }

        if (typeof OneSignal.requestPermission === 'function') {
            const response = await OneSignal.requestPermission({ fallbackToSettings: true });
            const accepted = typeof response === 'boolean'
                ? response
                : Boolean(response?.permission || response?.accepted);
            if (accepted) {
                await ensureNativePushOptIn(OneSignal);
            }
            return accepted;
        }

        return false;
    }

    async function autoPromptNativePush(OneSignal) {
        if (!getNativeOneSignalPlugin() || hasAutoPromptedNativePush()) {
            return;
        }

        try {
            if (await nativeHasPermission(OneSignal)) {
                markAutoPromptedNativePush();
                return;
            }

            if (!await nativeCanRequestPermission(OneSignal)) {
                return;
            }

            const accepted = await requestNativePermission(OneSignal);
            markAutoPromptedNativePush();
            setStatus(accepted ? 'Push notifications are enabled for this device.' : 'Notifications were not enabled.', !accepted);
        } catch (error) {
            setStatus('Notifications could not be enabled on this device.', true);
        }
    }

    async function refreshToggleState(toggleInput) {
        toggleInput.disabled = true;
        let keepDisabled = false;

        try {
            const OneSignal = await ensureOneSignal();
            if (!OneSignal) {
                keepDisabled = true;
                setStatus('Push notifications are not configured yet.', true);
                return;
            }

            await syncToggleState(OneSignal, toggleInput);
        } catch (error) {
            toggleInput.checked = false;
        } finally {
            toggleInput.disabled = keepDisabled;
        }
    }

    async function initToggle(root = document) {
        const toggleInput = getToggle(root);
        if (!toggleInput) {
            return;
        }

        if (!initializedToggles.has(toggleInput)) {
            initializedToggles.add(toggleInput);
            toggleInput.addEventListener('change', async () => {
                const shouldEnable = toggleInput.checked;
                toggleInput.disabled = true;
                setStatus(shouldEnable ? 'Opening notification permission prompt...' : 'Turning off push notifications...', false);

                try {
                    const OneSignal = await ensureOneSignal();
                    if (!OneSignal) {
                        toggleInput.checked = false;
                        setStatus('Push notifications are not configured yet.', true);
                        return;
                    }

                    if (getNativeOneSignalPlugin()) {
                        if (shouldEnable) {
                            const accepted = await requestNativePermission(OneSignal);
                            toggleInput.checked = accepted && await nativePushIsEnabledOnThisDevice(OneSignal);
                            setStatus(toggleInput.checked ? 'Push notifications are enabled for this device.' : 'Notifications were not enabled.', !toggleInput.checked);
                        } else {
                            await optOutNativePush(OneSignal);
                            toggleInput.checked = false;
                            setStatus('Push notifications are off for this device.', false);
                        }
                    } else if (shouldEnable) {
                        const accepted = await enableWebPushInThisBrowser(OneSignal);
                        toggleInput.checked = accepted;
                        setStatus(accepted ? 'Push notifications are enabled for this browser.' : 'Notifications were not enabled.', !accepted);
                    } else if (await disableWebPushInThisBrowser(OneSignal)) {
                        toggleInput.checked = false;
                        setStatus('Push notifications are off for this browser.', false);
                    } else {
                        toggleInput.checked = true;
                        setStatus('Use your browser settings to turn off web push notifications.', true);
                    }
                } catch (error) {
                    const OneSignal = await ensureOneSignal().catch(() => null);
                    if (OneSignal) {
                        await syncToggleState(OneSignal, toggleInput).catch(() => {
                            toggleInput.checked = !shouldEnable;
                        });
                    } else {
                        toggleInput.checked = !shouldEnable;
                    }
                    setStatus('Notifications could not be updated on this device.', true);
                } finally {
                    toggleInput.disabled = false;
                }
            });
        }

        await refreshToggleState(toggleInput);
    }

    function refreshVisibleToggle() {
        initToggle(document);
    }

    ensureOneSignal()
        .then(async (OneSignal) => {
            if (OneSignal) {
                await autoPromptNativePush(OneSignal);
            }
            await initToggle(document);
        })
        .catch((error) => {
            console.error('CraftCrawl OneSignal initialization failed:', error);
            const toggleInput = getToggle();
            if (toggleInput) {
                toggleInput.disabled = true;
                setStatus(error && error.message ? error.message : 'Push notifications could not be initialized.', true);
            }
        });

    document.addEventListener('craftcrawl:user-shell-navigated', refreshVisibleToggle);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshVisibleToggle();
        }
    });
    window.addEventListener('pageshow', refreshVisibleToggle);
    window.addEventListener('focus', refreshVisibleToggle);
    window.CraftCrawlInitOneSignalPush = initToggle;
})();
