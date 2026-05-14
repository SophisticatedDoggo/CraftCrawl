(function () {
    const widget = document.querySelector('[data-analytics-widget]');

    if (!widget) {
        return;
    }

    const endpoint = widget.dataset.analyticsEndpoint || 'analytics_data.php';
    const modeButtons = Array.from(widget.querySelectorAll('[data-analytics-mode]'));
    const previousButton = widget.querySelector('[data-analytics-previous]');
    const nextButton = widget.querySelector('[data-analytics-next]');
    const periodLabel = widget.querySelector('[data-analytics-period-label]');
    const totalLabel = widget.querySelector('[data-analytics-total-label]');
    const chart = widget.querySelector('[data-analytics-chart]');
    const summaryCards = widget.querySelector('[data-analytics-summary-cards]');
    const visitors = document.querySelector('[data-analytics-top-visitors]');
    let mode = widget.dataset.analyticsMode || 'month';
    let offset = 0;

    function escapeHtml(value) {
        const element = document.createElement('span');
        element.textContent = value || '';
        return element.innerHTML;
    }

    function setLoading(isLoading) {
        widget.classList.toggle('is-loading', isLoading);
        widget.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    function chartPath(points, width, height, padding, maxValue) {
        if (!points.length) {
            return '';
        }

        return points.map((point, index) => {
            const x = points.length === 1
                ? width / 2
                : padding + ((width - padding * 2) * index) / (points.length - 1);
            const y = height - padding - ((height - padding * 2) * point.total) / maxValue;
            return `${index === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`;
        }).join(' ');
    }

    function shouldShowPointLabel(points, index) {
        if (points.length <= 12) {
            return true;
        }

        const interval = Math.ceil(points.length / 6);
        return index === 0 || index === points.length - 1 || index % interval === 0;
    }

    function renderChart(points) {
        const width = 640;
        const height = 300;
        const padding = 46;
        const maxValue = Math.max(1, ...points.map((point) => Number(point.total || 0)));
        const path = chartPath(points, width, height, padding, maxValue);
        const areaPath = path
            ? `${path} L ${width - padding} ${height - padding} L ${padding} ${height - padding} Z`
            : '';

        chart.innerHTML = `
            <svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Check-ins for selected period">
                <text class="analytics-line-axis-label analytics-line-axis-label-y" x="14" y="${height / 2}" text-anchor="middle">Check-ins</text>
                <line class="analytics-line-axis" x1="${padding}" y1="${height - padding}" x2="${width - padding}" y2="${height - padding}"></line>
                <line class="analytics-line-axis" x1="${padding}" y1="${padding}" x2="${padding}" y2="${height - padding}"></line>
                ${areaPath ? `<path class="analytics-line-area" d="${areaPath}"></path>` : ''}
                ${path ? `<path class="analytics-line-path" d="${path}"></path>` : ''}
                ${points.map((point, index) => {
                    const x = points.length === 1
                        ? width / 2
                        : padding + ((width - padding * 2) * index) / (points.length - 1);
                    const y = height - padding - ((height - padding * 2) * point.total) / maxValue;
                    const count = Number(point.total || 0);
                    const label = shouldShowPointLabel(points, index)
                        ? `<text class="analytics-line-label" x="${x.toFixed(1)}" y="${height - 18}" text-anchor="middle">${escapeHtml(point.axis_label || point.label)}</text>`
                        : '';

                    return `
                        <circle class="analytics-line-point" cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="5">
                            <title>${escapeHtml(point.label)}: ${count} check-in${count === 1 ? '' : 's'}</title>
                        </circle>
                        ${label}
                    `;
                }).join('')}
            </svg>
        `;
    }

    function renderVisitors(items) {
        if (!visitors) {
            return;
        }

        if (!items.length) {
            visitors.innerHTML = '<p class="analytics-empty">Top visitors will appear after check-ins are recorded in this period.</p>';
            return;
        }

        visitors.innerHTML = items.map((visitor) => {
            const frame = String(visitor.frame || '').replace(/[^a-z0-9_-]/gi, '');
            const avatarClass = `user-avatar user-avatar-small ${frame ? `has-frame-${frame}` : ''}`;
            const avatar = visitor.avatar_url
                ? `<span class="${avatarClass}"><img src="${escapeHtml(visitor.avatar_url)}" alt="${escapeHtml(visitor.name)} profile photo" loading="lazy"></span>`
                : `<span class="${avatarClass}" aria-label="${escapeHtml(visitor.name)} profile photo"><span>${escapeHtml(visitor.initials || 'CC')}</span></span>`;

            return `
            <div class="analytics-list-item">
                <div class="user-identity-row">
                    ${avatar}
                    <div>
                        <strong>${escapeHtml(visitor.name)}</strong>
                        <span>${escapeHtml(visitor.last_checkin)}</span>
                    </div>
                </div>
                <b>${escapeHtml(visitor.visit_label)}</b>
            </div>
        `;
        }).join('');
    }

    function renderSummaryCards(items) {
        if (!summaryCards) {
            return;
        }

        if (!items.length) {
            summaryCards.innerHTML = '<p class="analytics-empty">Summary will appear after check-ins are recorded.</p>';
            return;
        }

        summaryCards.innerHTML = items.map((item) => `
            <article class="analytics-card">
                <span>${escapeHtml(item.label)}</span>
                <strong>${escapeHtml(item.value)}</strong>
                <p>${escapeHtml(item.description)}</p>
            </article>
        `).join('');
    }

    function updateModeButtons() {
        modeButtons.forEach((button) => {
            button.classList.toggle('is-active', button.dataset.analyticsMode === mode);
        });
    }

    function loadAnalytics() {
        setLoading(true);
        updateModeButtons();

        fetch(`${endpoint}?mode=${encodeURIComponent(mode)}&offset=${encodeURIComponent(offset)}`, {
            credentials: 'same-origin'
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) {
                    throw new Error(data.message || 'Analytics could not be loaded.');
                }

                periodLabel.textContent = data.period_label;
                totalLabel.textContent = data.total_label;
                nextButton.disabled = !data.can_go_next;
                previousButton.disabled = !data.can_go_previous;
                renderChart(data.points || []);
                renderSummaryCards(data.summary_cards || []);
                renderVisitors(data.top_visitors || []);
            })
            .catch(() => {
                chart.innerHTML = '<p class="analytics-empty">Analytics could not be loaded.</p>';
            })
            .finally(() => {
                setLoading(false);
            });
    }

    modeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            mode = button.dataset.analyticsMode;
            offset = 0;
            loadAnalytics();
        });
    });

    previousButton.addEventListener('click', () => {
        offset -= 1;
        loadAnalytics();
    });

    nextButton.addEventListener('click', () => {
        if (offset < 0) {
            offset += 1;
            loadAnalytics();
        }
    });

    loadAnalytics();
})();
