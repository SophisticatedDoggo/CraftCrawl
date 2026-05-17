(function () {
    const toggleInput = document.querySelector('[data-onesignal-toggle]');
    const statusMessage = document.querySelector('[data-onesignal-status]');
    const isUserPath = /\/user\/?$|\/user\//.test(window.location.pathname);
    const nativeAutoPromptKey = 'craftcrawl_native_push_auto_prompted_v2';
    const nativePushDisabledKey = 'craftcrawl_native_push_disabled_v1';

    function userEndpoint(file) {
        return isUserPath ? file : `user/${file}`;
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

        // Bundled OneSignal clients expose the rich namespace API already.
        if (rawPlugin.Notifications && rawPlugin.User?.pushSubscription) {
            return rawPlugin;
        }

        // Remote web content inside Capacitor sees the raw native bridge instead.
        // Adapt that bridge to the smaller OneSignal shape the rest of this file uses.
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
                    return Boolean((await rawPlugin.requestPermission({ fallbackToSettings })).accepted);
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

    function hasDisabledNativePush() {
        try {
            return window.localStorage.getItem(nativePushDisabledKey) === '1';
        } catch (error) {
            return false;
        }
    }

    function markDisabledNativePush() {
        try {
            window.localStorage.setItem(nativePushDisabledKey, '1');
        } catch (error) {
            // The native opt-out still applies even if local persistence is unavailable.
        }
    }

    function clearDisabledNativePush() {
        try {
            window.localStorage.removeItem(nativePushDisabledKey);
        } catch (error) {
            // Ignore storage failures; native permission still remains the main gate.
        }
    }

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

    async function syncNativePushSubscriptionState(OneSignal) {
        if (!getNativeOneSignalPlugin()) {
            return false;
        }

        const hasPermission = await nativeHasPermission(OneSignal);
        const shouldBeEnabled = hasPermission && !hasDisabledNativePush();

        // Keep the actual device subscription healthy on every app page, not only
        // when the user opens Settings. iOS permission and OneSignal subscription
        // can briefly drift apart after a fresh install or app restart.
        if (shouldBeEnabled && !await nativeIsOptedIn(OneSignal)) {
            await ensureNativePushOptIn(OneSignal);
        }

        return shouldBeEnabled;
    }

    async function syncToggleState(OneSignal) {
        if (!toggleInput) {
            return;
        }

        toggleInput.checked = getNativeOneSignalPlugin()
            ? await syncNativePushSubscriptionState(OneSignal)
            : window.Notification?.permission === 'granted';
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

        // Older native bridges exposed this lower-level plugin method directly.
        // Keep it as a fallback so existing installed builds fail gracefully.
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
            if (accepted) {
                clearDisabledNativePush();
            }
            setStatus(accepted ? 'Push notifications are enabled for this device.' : 'Notifications were not enabled.', !accepted);
        } catch (error) {
            setStatus('Notifications could not be enabled on this device.', true);
        }
    }

    fetch(userEndpoint('onesignal_config.php'), { credentials: 'same-origin' })
        .then((response) => response.ok ? response.json() : null)
        .then((config) => {
            if (!config || !config.enabled || !config.app_id) {
                if (toggleInput) {
                    toggleInput.disabled = true;
                    setStatus('Push notifications are not configured yet.', true);
                }
                return null;
            }

            return getNativeOneSignalPlugin()
                ? initNativeOneSignal(config)
                : initOneSignal(config);
        })
        .then(async (OneSignal) => {
            if (!OneSignal) {
                return;
            }

            await autoPromptNativePush(OneSignal);
            await syncNativePushSubscriptionState(OneSignal);

            if (!toggleInput) {
                return;
            }

            syncToggleState(OneSignal)
                .catch(() => {
                    toggleInput.checked = false;
                })
                .finally(() => {
                    toggleInput.disabled = false;
                });

            toggleInput.addEventListener('change', async () => {
                const shouldEnable = toggleInput.checked;
                toggleInput.disabled = true;
                setStatus(shouldEnable ? 'Opening notification permission prompt...' : 'Turning off push notifications...', false);

                try {
                    if (getNativeOneSignalPlugin()) {
                        if (shouldEnable) {
                            const accepted = await requestNativePermission(OneSignal);
                            if (accepted) {
                                clearDisabledNativePush();
                            }
                            toggleInput.checked = accepted;
                            setStatus(toggleInput.checked ? 'Push notifications are enabled for this device.' : 'Notifications were not enabled.', !toggleInput.checked);
                        } else {
                            await optOutNativePush(OneSignal);
                            markDisabledNativePush();
                            toggleInput.checked = false;
                            setStatus('Push notifications are off for this device.', false);
                        }
                    } else if (shouldEnable) {
                        const accepted = await requestPermission(OneSignal);
                        toggleInput.checked = accepted;
                        setStatus(accepted ? 'Push notifications are enabled for this device.' : 'Notifications were not enabled.', !accepted);
                    } else {
                        toggleInput.checked = true;
                        setStatus('Use your browser settings to turn off web push notifications.', true);
                    }
                } catch (error) {
                    await syncToggleState(OneSignal).catch(() => {
                        toggleInput.checked = !shouldEnable;
                    });
                    setStatus('Notifications could not be updated on this device.', true);
                } finally {
                    toggleInput.disabled = false;
                }
            });
        })
        .catch((error) => {
            console.error('CraftCrawl OneSignal initialization failed:', error);
            if (toggleInput) {
                toggleInput.disabled = true;
                setStatus(error && error.message ? error.message : 'Push notifications could not be initialized.', true);
            }
        });
})();
