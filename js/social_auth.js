(function () {
    const config = window.CRAFTCRAWL_SOCIAL_AUTH || {};
    const feedback = document.querySelector('[data-social-auth-feedback]');
    let googleInitialized = false;
    let appleInitialized = false;

    function showMessage(message) {
        if (!feedback) {
            return;
        }

        feedback.textContent = message || 'Sign-in failed. Please try again.';
        feedback.hidden = false;
    }

    function submitCredential(provider, credential, extraFields) {
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
            credentials: 'same-origin'
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
        if (!target || !config.googleClientId || !window.google || !google.accounts || !google.accounts.id) {
            return false;
        }

        google.accounts.id.initialize({
            client_id: config.googleClientId,
            callback: (response) => {
                if (response && response.credential) {
                    submitCredential('google', response.credential);
                } else {
                    showMessage('Google sign-in did not return account credentials.');
                }
            }
        });

        google.accounts.id.renderButton(target, {
            type: 'standard',
            theme: 'outline',
            size: 'large',
            text: 'signin_with',
            shape: 'rectangular',
            logo_alignment: 'left',
            width: Math.min(400, target.clientWidth || 360)
        });
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

    window.addEventListener('load', () => {
        let attempts = 0;
        const timer = window.setInterval(() => {
            attempts += 1;
            const googleReady = !config.googleClientId || initGoogle();
            const appleReady = !config.appleClientId || initApple();

            if ((googleReady && appleReady) || attempts >= 20) {
                window.clearInterval(timer);
            }
        }, 150);
    });
})();
