(function () {
    function removeExistingCelebration() {
        const existing = document.querySelector('[data-level-celebration]');

        if (existing) {
            existing.remove();
        }
    }

    function createConfetti(layer) {
        const colors = ['#f04f64', '#ffb23f', '#35b779', '#2783d9', '#7c5cff', '#f7d34a'];

        for (let index = 0; index < 80; index++) {
            const piece = document.createElement('span');
            const size = 6 + Math.random() * 8;

            piece.className = 'level-confetti-piece';
            piece.style.left = `${Math.random() * 100}%`;
            piece.style.width = `${size}px`;
            piece.style.height = `${size * 1.6}px`;
            piece.style.backgroundColor = colors[index % colors.length];
            piece.style.animationDelay = `${Math.random() * 0.55}s`;
            piece.style.animationDuration = `${2.1 + Math.random() * 1.3}s`;
            piece.style.setProperty('--confetti-drift', `${(Math.random() * 220) - 110}px`);
            piece.style.setProperty('--confetti-spin', `${360 + Math.random() * 720}deg`);
            layer.appendChild(piece);
        }
    }

    function createBalloons(layer) {
        const colors = ['#f04f64', '#ffb23f', '#35b779', '#2783d9', '#7c5cff'];

        for (let index = 0; index < 9; index++) {
            const balloon = document.createElement('span');

            balloon.className = 'level-balloon';
            balloon.style.left = `${8 + (index * 10) + Math.random() * 5}%`;
            balloon.style.backgroundColor = colors[index % colors.length];
            balloon.style.animationDelay = `${Math.random() * 0.45}s`;
            balloon.style.animationDuration = `${4.4 + Math.random() * 1.4}s`;
            layer.appendChild(balloon);
        }
    }

    function showLevelCelebration(levelUp) {
        if (!levelUp || !levelUp.level) {
            return;
        }

        removeExistingCelebration();

        const overlay = document.createElement('div');
        const effects = document.createElement('div');
        const panel = document.createElement('section');
        const closeButton = document.createElement('button');
        const kicker = document.createElement('span');
        const heading = document.createElement('h2');
        const message = document.createElement('p');

        overlay.className = 'level-celebration';
        overlay.dataset.levelCelebration = 'true';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'level-celebration-title');

        effects.className = 'level-celebration-effects';
        panel.className = 'level-celebration-panel';
        closeButton.className = 'level-celebration-close';
        closeButton.type = 'button';
        closeButton.setAttribute('aria-label', 'Close celebration');
        closeButton.textContent = '\u00d7';

        kicker.textContent = 'Level Up';
        heading.id = 'level-celebration-title';
        heading.textContent = `You reached Level ${levelUp.level}`;
        message.textContent = levelUp.title
            ? `Congratulations, ${levelUp.title}. Keep the crawl going.`
            : 'Congratulations. Keep the crawl going.';

        createConfetti(effects);
        createBalloons(effects);
        panel.append(closeButton, kicker, heading, message);
        overlay.append(effects, panel);
        document.body.appendChild(overlay);
        document.body.classList.add('level-celebration-open');

        function closeCelebration() {
            overlay.classList.add('is-closing');
            document.body.classList.remove('level-celebration-open');
            window.setTimeout(() => overlay.remove(), 220);
        }

        closeButton.addEventListener('click', closeCelebration);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                closeCelebration();
            }
        });

        window.setTimeout(closeCelebration, 6500);
    }

    window.craftcrawlShowLevelCelebration = showLevelCelebration;
}());
