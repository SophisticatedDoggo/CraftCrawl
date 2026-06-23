(function () {
    if (localStorage.getItem('craftcrawl_age_verified')) {
        return;
    }

    var style = document.createElement('style');
    style.textContent = 'body > *:not(.age-gate-overlay) { display: none !important; }';
    document.head.appendChild(style);

    function buildOverlay() {
        var overlay = document.createElement('div');
        overlay.className = 'age-gate-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Age verification');

        var card = document.createElement('div');
        card.className = 'age-gate-card';

        var logo = document.querySelector('img.site-logo');
        if (logo) {
            var logoClone = logo.cloneNode(true);
            logoClone.className = 'site-logo age-gate-logo';
            card.appendChild(logoClone);
        }

        var heading = document.createElement('h1');
        heading.textContent = 'Are you 21 or older?';
        card.appendChild(heading);

        var subtext = document.createElement('p');
        subtext.className = 'age-gate-subtext';
        subtext.textContent = 'You must be of legal drinking age to use CraftCrawl.';
        card.appendChild(subtext);

        var actions = document.createElement('div');
        actions.className = 'age-gate-actions';

        var yesBtn = document.createElement('button');
        yesBtn.type = 'button';
        yesBtn.className = 'age-gate-btn age-gate-btn-yes';
        yesBtn.textContent = 'Yes, I\'m 21+';

        var noBtn = document.createElement('button');
        noBtn.type = 'button';
        noBtn.className = 'age-gate-btn age-gate-btn-no';
        noBtn.textContent = 'No';

        actions.appendChild(yesBtn);
        actions.appendChild(noBtn);
        card.appendChild(actions);
        overlay.appendChild(card);

        yesBtn.addEventListener('click', function () {
            localStorage.setItem('craftcrawl_age_verified', 'true');
            style.remove();
            overlay.remove();
        });

        noBtn.addEventListener('click', function () {
            heading.textContent = 'You must be 21 or older to use CraftCrawl.';
            subtext.textContent = 'Please close this page.';
            actions.remove();
        });

        document.body.appendChild(overlay);
        yesBtn.focus();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', buildOverlay);
    } else {
        buildOverlay();
    }
})();
