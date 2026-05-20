(function () {
    const config = window.CRAFTCRAWL_SOCIAL_AUTH || {};
    const socialOptions = document.querySelector('[data-social-auth-options]');
    const feedback = document.querySelector('[data-social-auth-feedback]');
    let googleInitialized = false;
    let appleInitialized = false;
    let socialAuthBusy = false;
    let socialAuthBusyTimer = null;
    let initTimer = null;
    let initAttempts = 0;

    function setSocialAuthBusy(isBusy, message) {
        socialAuthBusy = isBusy;

        if (socialOptions) {
            socialOptions.classList.remove('is-previewing');
            socialOptions.classList.toggle('is-busy', isBusy);
            socialOptions.setAttribute('aria-busy', isBusy ? 'true' : 'false');
        }

        if (feedback && message) {
            feedback.classList.remove('is-error');
            feedback.textContent = message;
            feedback.hidden = false;
        }
    }

    function holdSocialAuth(message, delay) {
        window.clearTimeout(socialAuthBusyTimer);
        setSocialAuthBusy(true, message);

        socialAuthBusyTimer = window.setTimeout(() => {
            setSocialAuthBusy(false);
            if (feedback && !feedback.classList.contains('is-error')) {
                feedback.hidden = true;
            }
        }, delay || 3500);
    }

    function previewSocialAuthMessage(message) {
        if (!feedback || socialAuthBusy) {
            return;
        }

        if (socialOptions) {
            socialOptions.classList.add('is-previewing');
        }

        feedback.classList.remove('is-error');
        feedback.textContent = message;
        feedback.hidden = false;
    }

    function clearSocialAuthBusy() {
        window.clearTimeout(socialAuthBusyTimer);
        setSocialAuthBusy(false);
    }

    function showMessage(message) {
        clearSocialAuthBusy();

        if (!feedback) {
            return;
        }

        feedback.classList.add('is-error');
        feedback.textContent = message || 'Sign-in failed. Please try again.';
        feedback.hidden = false;
    }

    function getSocialButtonWidth(target) {
        const container = target.closest('[data-social-auth-options]');
        const width = target.getBoundingClientRect().width || target.clientWidth || (container && container.getBoundingClientRect().width) || 400;
        return Math.min(400, Math.floor(width));
    }

    function isAppleDevice() {
        const platform = navigator.platform || '';
        const userAgent = navigator.userAgent || '';
        const hasTouch = navigator.maxTouchPoints && navigator.maxTouchPoints > 1;

        return /iPad|iPhone|iPod/.test(userAgent) || (platform === 'MacIntel' && hasTouch);
    }

    function isNativeApp() {
        const capacitor = window.Capacitor;
        return Boolean(capacitor && capacitor.isNativePlatform?.() && capacitor.getPlatform?.() === 'ios');
    }

    function getAbsoluteAuthUrl() {
        return new URL(config.authUrl || 'social_login.php', window.location.href).toString();
    }

    function getAbsoluteLoginUrl() {
        return new URL(window.location.pathname + window.location.search, window.location.href).toString();
    }

    function showNativeGoogleFallback(target) {
        if (!target || target.querySelector('[data-google-native-fallback]')) {
            return;
        }

        target.classList.add('is-fallback');
        target.textContent = '';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'social-auth-native-fallback';
        button.dataset.googleNativeFallback = 'true';
        button.textContent = 'Sign in with Google';
        target.appendChild(button);

        button.addEventListener('click', () => {
            const capacitor = window.Capacitor;
            const Browser = capacitor?.Plugins?.Browser
                || (typeof capacitor?.registerPlugin === 'function' ? capacitor.registerPlugin('Browser') : null);

            showMessage('Google sign-in opens in a secure browser on iOS.');

            if (Browser && typeof Browser.open === 'function') {
                Browser.open({ url: getAbsoluteLoginUrl() }).catch(() => {
                    window.location.href = getAbsoluteLoginUrl();
                });
                return;
            }

            window.location.href = getAbsoluteLoginUrl();
        });
    }

    function submitCredential(provider, credential, extraFields) {
        if (socialAuthBusy && provider !== 'apple') {
            return Promise.resolve();
        }

        setSocialAuthBusy(true, 'Signing you in...');

        const formData = new FormData();
        formData.append('csrf_token', config.csrfToken || '');
        formData.append('provider', provider);
        formData.append('credential', credential);

        Object.entries(extraFields || {}).forEach(([key, value]) => {
            if (value) {
                formData.append(key, value);
            }
        });

        return fetch(config.authUrl || 'social_login.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then((response) => response.json().catch(() => null).then((body) => {
                if (!response.ok || !body || !body.success) {
                    throw new Error((body && body.message) || 'Sign-in failed. Please try again.');
                }

                window.location.assign(body.redirect || 'user/portal.php');
            }))
            .catch((error) => {
                showMessage(error.message);
            });
    }

    function initGoogle() {
        if (googleInitialized) {
            return true;
        }

        const target = document.querySelector('[data-google-signin]');
        if (!target || !config.googleClientId) {
            return false;
        }

        if ((!window.google || !google.accounts || !google.accounts.id) && isNativeApp()) {
            showNativeGoogleFallback(target);
        }

        if (!window.google || !google.accounts || !google.accounts.id) {
            return false;
        }

        const initOptions = {
            client_id: config.googleClientId,
            itp_support: true,
            callback: (response) => {
                if (response && response.credential) {
                    setSocialAuthBusy(false);
                    submitCredential('google', response.credential);
                } else {
                    showMessage('Google sign-in did not return account credentials.');
                }
            }
        };

        if (isAppleDevice() || isNativeApp()) {
            initOptions.ux_mode = 'redirect';
            initOptions.login_uri = getAbsoluteAuthUrl();
        }

        google.accounts.id.initialize(initOptions);

        google.accounts.id.renderButton(target, {
            type: 'standard',
            theme: 'outline',
            size: 'large',
            text: 'signin_with',
            shape: 'rectangular',
            logo_alignment: 'left',
            width: getSocialButtonWidth(target)
        });

        if (isNativeApp()) {
            window.setTimeout(() => {
                if (!target.querySelector('iframe')) {
                    showNativeGoogleFallback(target);
                }
            }, 800);
        }

        target.addEventListener('pointerdown', () => {
            previewSocialAuthMessage('Opening Google sign-in...');
        }, true);

        target.addEventListener('click', () => {
            if (!socialAuthBusy) {
                holdSocialAuth('Opening Google sign-in...', 4500);
            }
        }, true);

        googleInitialized = true;
        return true;
    }

    function initApple() {
        if (appleInitialized) {
            return true;
        }

        const target = document.querySelector('[data-apple-signin]');
        if (!target || !config.appleClientId || !window.AppleID || !AppleID.auth) {
            return false;
        }

        AppleID.auth.init({
            clientId: config.appleClientId,
            scope: 'name email',
            redirectURI: config.appleRedirectUri || window.location.href,
            state: config.csrfToken || '',
            usePopup: true
        });

        if (typeof AppleID.auth.renderButton === 'function') {
            AppleID.auth.renderButton();
        }

        target.addEventListener('pointerdown', () => {
            previewSocialAuthMessage('Opening Apple sign-in...');
        }, true);

        target.addEventListener('click', () => {
            if (!socialAuthBusy) {
                holdSocialAuth('Opening Apple sign-in...', 4500);
            }
        }, true);

        document.addEventListener('AppleIDSignInOnSuccess', (event) => {
            const detail = event.detail || {};
            const authorization = detail.authorization || {};
            const user = detail.user || {};
            const name = user.name || {};

            if (authorization.id_token) {
                submitCredential('apple', authorization.id_token, {
                    first_name: name.firstName || '',
                    last_name: name.lastName || ''
                });
            } else {
                showMessage('Apple sign-in did not return account credentials.');
            }
        });

        document.addEventListener('AppleIDSignInOnFailure', () => {
            showMessage('Apple sign-in was canceled or could not be completed.');
        });
        appleInitialized = true;
        return true;
    }

    function initializeSocialAuth() {
        initAttempts += 1;

        const googleReady = !config.googleClientId || initGoogle();
        const appleReady = !config.appleClientId || initApple();

        if ((googleReady && appleReady) || initAttempts >= 80) {
            window.clearInterval(initTimer);
            initTimer = null;
            return true;
        }

        return false;
    }

    function startSocialAuthInit() {
        if (initTimer || (googleInitialized && appleInitialized)) {
            return;
        }

        if (!initializeSocialAuth()) {
            initTimer = window.setInterval(initializeSocialAuth, 150);
        }
    }

    if (document.readyState === 'complete') {
        startSocialAuthInit();
    } else {
        window.addEventListener('load', startSocialAuthInit);
    }

    window.addEventListener('pageshow', startSocialAuthInit);
})();
