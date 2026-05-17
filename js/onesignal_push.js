(function () {
    const isUserPath = /\/user\/?$|\/user\//.test(window.location.pathname);
    const nativeAutoPromptKey = 'craftcrawl_native_push_auto_prompted_v2';
    let oneSignalPromise = null;

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
                    optIn() {
                        return rawPlugin.optInPushSubscription();
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

    async function ensureNativePushOptIn(OneSignal) {
        if (OneSignal.User?.pushSubscription && typeof OneSignal.User.pushSubscription.optIn === 'function') {
            await OneSignal.User.pushSubscription.optIn();
        }
    }

    async function requestNativePermission(OneSignal, fallbackToSettings = false) {
        if (await nativeHasPermission(OneSignal)) {
            await ensureNativePushOptIn(OneSignal);
            return true;
        }

        if (OneSignal.Notifications && typeof OneSignal.Notifications.requestPermission === 'function') {
            const accepted = Boolean(await OneSignal.Notifications.requestPermission(fallbackToSettings));
            if (accepted) {
                await ensureNativePushOptIn(OneSignal);
            }
            return accepted;
        }

        if (typeof OneSignal.requestPermission === 'function') {
            const response = await OneSignal.requestPermission({ fallbackToSettings });
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

    async function syncNativePushWithDevicePermission(OneSignal) {
        if (!getNativeOneSignalPlugin()) {
            return;
        }

        try {
            if (await nativeHasPermission(OneSignal)) {
                await ensureNativePushOptIn(OneSignal);
                markAutoPromptedNativePush();
                return;
            }

            if (hasAutoPromptedNativePush()) {
                return;
            }

            if (!await nativeCanRequestPermission(OneSignal)) {
                return;
            }

            await requestNativePermission(OneSignal);
            markAutoPromptedNativePush();
        } catch (error) {
            console.error('CraftCrawl native push permission sync failed:', error);
        }
    }

    async function refreshNativePushState() {
        try {
            const OneSignal = await ensureOneSignal();
            if (OneSignal) {
                await syncNativePushWithDevicePermission(OneSignal);
            }
        } catch (error) {
            console.error('CraftCrawl native push refresh failed:', error);
        }
    }

    ensureOneSignal()
        .then(async (OneSignal) => {
            if (OneSignal) {
                await syncNativePushWithDevicePermission(OneSignal);
            }
        })
        .catch((error) => {
            console.error('CraftCrawl OneSignal initialization failed:', error);
        });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshNativePushState();
        }
    });
    window.addEventListener('pageshow', refreshNativePushState);
    window.addEventListener('focus', refreshNativePushState);
})();
