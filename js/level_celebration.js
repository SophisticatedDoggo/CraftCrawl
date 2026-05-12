(function () {
    const STEP_DELAY = 620;
    const STEP_DURATION = 850;

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

    function progressPercent(progress) {
        if (!progress || progress.max_level || !progress.next_level_xp) {
            return progress && progress.max_level ? 100 : 0;
        }

        return Math.min(100, Math.max(0, (Number(progress.level_xp || 0) / Number(progress.next_level_xp)) * 100));
    }

    function xpRequiredForLevel(level) {
        return Math.max(1, Number(level || 1)) * 100;
    }

    function setProgress(fill, xpText, progress, percentOverride) {
        const percent = percentOverride === undefined ? progressPercent(progress) : percentOverride;
        fill.style.width = `${percent}%`;

        if (progress.max_level) {
            xpText.textContent = 'Max level reached';
        } else {
            xpText.textContent = `${progress.level_xp} / ${progress.next_level_xp} XP`;
        }
    }

    function buildProgressSteps(before, after) {
        const steps = [];
        const startLevel = Number(before.level || 1);
        const endLevel = Number(after.level || startLevel);

        if (endLevel <= startLevel) {
            steps.push({
                type: 'fill',
                level: endLevel,
                title: after.title,
                progress: after,
                percent: progressPercent(after)
            });
            return steps;
        }

        for (let level = startLevel; level < endLevel; level++) {
            steps.push({
                type: 'fill',
                level,
                title: level === startLevel ? before.title : '',
                progress: {
                    level,
                    level_xp: xpRequiredForLevel(level),
                    next_level_xp: xpRequiredForLevel(level),
                    max_level: false
                },
                percent: 100
            });
            steps.push({
                type: 'level',
                level: level + 1,
                title: level + 1 === endLevel ? after.title : ''
            });
            steps.push({
                type: 'reset',
                level: level + 1,
                progress: {
                    level: level + 1,
                    level_xp: 0,
                    next_level_xp: xpRequiredForLevel(level + 1),
                    max_level: false
                },
                percent: 0
            });
        }

        steps.push({
            type: 'fill',
            level: endLevel,
            title: after.title,
            progress: after,
            percent: progressPercent(after)
        });
        return steps;
    }

    function animateProgress(parts, before, after, levelUp) {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const steps = buildProgressSteps(before, after);

        setProgress(parts.fill, parts.xpText, before);
        parts.levelNumber.textContent = before.level;
        parts.levelTitle.textContent = before.title || '';

        if (prefersReducedMotion) {
            parts.levelNumber.textContent = after.level;
            parts.levelTitle.textContent = after.title || '';
            setProgress(parts.fill, parts.xpText, after);
            return;
        }

        steps.forEach((step, index) => {
            window.setTimeout(() => {
                if (step.type === 'level') {
                    parts.levelNumber.classList.add('is-changing');
                    window.setTimeout(() => {
                        parts.levelNumber.textContent = step.level;
                        parts.levelTitle.textContent = step.title || parts.levelTitle.textContent;
                        parts.levelNumber.classList.remove('is-changing');
                        parts.levelNumber.classList.add('did-change');
                        window.setTimeout(() => parts.levelNumber.classList.remove('did-change'), 360);
                    }, 170);
                    return;
                }

                if (step.type === 'reset') {
                    parts.fill.classList.add('is-resetting');
                    setProgress(parts.fill, parts.xpText, step.progress, 0);
                    window.setTimeout(() => parts.fill.classList.remove('is-resetting'), 40);
                    return;
                }

                setProgress(parts.fill, parts.xpText, step.progress, step.percent);
            }, STEP_DELAY + (index * STEP_DURATION));
        });

        if (levelUp) {
            window.setTimeout(() => {
                parts.kicker.textContent = 'Level Up';
                parts.heading.textContent = `You reached Level ${after.level}`;
            }, STEP_DELAY + Math.max(0, steps.length - 2) * STEP_DURATION);
        }
    }

    function showXpReward(reward) {
        if (!reward || !reward.progress || !reward.progress_before) {
            return;
        }

        removeExistingCelebration();

        const levelUp = Number(reward.progress.level || 1) > Number(reward.progress_before.level || 1);
        const overlay = document.createElement('div');
        const effects = document.createElement('div');
        const panel = document.createElement('section');
        const closeButton = document.createElement('button');
        const kicker = document.createElement('span');
        const heading = document.createElement('h2');
        const xpAmount = document.createElement('strong');
        const message = document.createElement('p');
        const progressWrap = document.createElement('div');
        const progressMeta = document.createElement('div');
        const levelLabel = document.createElement('strong');
        const levelNumber = document.createElement('span');
        const levelTitle = document.createElement('span');
        const xpText = document.createElement('span');
        const bar = document.createElement('div');
        const fill = document.createElement('span');

        overlay.className = 'level-celebration xp-reward-celebration';
        overlay.dataset.levelCelebration = 'true';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'level-celebration-title');

        effects.className = 'level-celebration-effects';
        panel.className = 'level-celebration-panel xp-reward-panel';
        closeButton.className = 'level-celebration-close';
        closeButton.type = 'button';
        closeButton.setAttribute('aria-label', 'Close XP reward');
        closeButton.textContent = '\u00d7';

        kicker.textContent = levelUp ? 'XP Earned' : 'Nice Check-In';
        heading.id = 'level-celebration-title';
        heading.textContent = levelUp ? 'Level progress incoming' : 'XP added';
        xpAmount.className = 'xp-reward-amount';
        xpAmount.textContent = `+${reward.xp_awarded || 0} XP`;
        message.textContent = levelUp
            ? 'Watch your progress roll into the next level.'
            : 'Your progress is moving forward.';

        progressWrap.className = 'xp-reward-progress';
        progressMeta.className = 'xp-reward-progress-meta';
        levelLabel.className = 'xp-reward-level';
        levelNumber.className = 'xp-reward-level-number';
        levelTitle.className = 'xp-reward-level-title';
        xpText.className = 'xp-reward-text';
        bar.className = 'xp-reward-bar';

        levelLabel.append('Level ', levelNumber);
        progressMeta.append(levelLabel, levelTitle, xpText);
        bar.appendChild(fill);
        progressWrap.append(progressMeta, bar);

        createConfetti(effects);
        if (levelUp) {
            createBalloons(effects);
        }

        panel.append(closeButton, kicker, heading, xpAmount, message, progressWrap);
        overlay.append(effects, panel);
        document.body.appendChild(overlay);
        document.body.classList.add('level-celebration-open');

        animateProgress({
            kicker,
            heading,
            fill,
            levelNumber,
            levelTitle,
            xpText
        }, reward.progress_before, reward.progress, levelUp);

        function closeCelebration() {
            overlay.classList.add('is-closing');
            document.body.classList.remove('level-celebration-open');
            window.setTimeout(() => overlay.remove(), 220);
        }

        closeButton.addEventListener('click', closeCelebration);
    }

    function showLevelCelebration(levelUp) {
        if (!levelUp || !levelUp.level) {
            return;
        }

        showXpReward({
            xp_awarded: 0,
            progress_before: {
                level: Math.max(1, Number(levelUp.level) - 1),
                title: '',
                level_xp: 0,
                next_level_xp: 1,
                max_level: false
            },
            progress: {
                level: Number(levelUp.level),
                title: levelUp.title || '',
                level_xp: 0,
                next_level_xp: 1,
                max_level: false
            }
        });
    }

    window.craftcrawlShowXpReward = showXpReward;
    window.craftcrawlShowLevelCelebration = showLevelCelebration;
}());
