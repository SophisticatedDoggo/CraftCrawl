window.CraftCrawlInitBusinessAnalytics = function (root = document) {
    const widget = root.querySelector('[data-analytics-widget]');

    if (!widget || widget.dataset.shellReady === 'true') { return; }
    widget.dataset.shellReady = 'true';

    const endpoint = widget.dataset.analyticsEndpoint || 'analytics_data.php';
    const modeButtons = Array.from(widget.querySelectorAll('[data-analytics-mode]'));
    const previousButton = widget.querySelector('[data-analytics-previous]');
    const nextButton = widget.querySelector('[data-analytics-next]');
    const periodLabel = widget.querySelector('[data-analytics-period-label]');
    const totalLabel = widget.querySelector('[data-analytics-total-label]');
    const chart = widget.querySelector('[data-analytics-chart]');
    const summaryCards = root.querySelector('[data-analytics-summary-cards]');
    const visitors = root.querySelector('[data-analytics-top-visitors]');
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

    function pointCoords(points, width, height, padding, maxValue) {
        return points.map((point, index) => {
            const x = points.length === 1
                ? width / 2
                : padding + ((width - padding * 2) * index) / (points.length - 1);
            const y = height - padding - ((height - padding * 2) * Number(point.total || 0)) / maxValue;
            return { x, y };
        });
    }

    function chartPath(coords) {
        if (!coords.length) {
            return '';
        }

        if (coords.length === 1) {
            return `M ${coords[0].x.toFixed(1)} ${coords[0].y.toFixed(1)}`;
        }

        // Monotone cubic spline interpolation (Fritsch-Carlson)
        const n = coords.length;
        const dx = [];
        const dy = [];
        const m = [];

        for (let i = 0; i < n - 1; i++) {
            dx.push(coords[i + 1].x - coords[i].x);
            dy.push(coords[i + 1].y - coords[i].y);
        }

        const slopes = dx.map((d, i) => d === 0 ? 0 : dy[i] / d);

        m.push(slopes[0]);
        for (let i = 1; i < n - 1; i++) {
            if (slopes[i - 1] * slopes[i] <= 0) {
                m.push(0);
            } else {
                m.push((slopes[i - 1] + slopes[i]) / 2);
            }
        }
        m.push(slopes[slopes.length - 1]);

        // Enforce monotonicity
        for (let i = 0; i < n - 1; i++) {
            if (slopes[i] === 0) {
                m[i] = 0;
                m[i + 1] = 0;
            } else {
                const alpha = m[i] / slopes[i];
                const beta = m[i + 1] / slopes[i];
                const tau = alpha * alpha + beta * beta;
                if (tau > 9) {
                    const s = 3 / Math.sqrt(tau);
                    m[i] = s * alpha * slopes[i];
                    m[i + 1] = s * beta * slopes[i];
                }
            }
        }

        let path = `M ${coords[0].x.toFixed(1)} ${coords[0].y.toFixed(1)}`;
        for (let i = 0; i < n - 1; i++) {
            const seg = dx[i] / 3;
            const cp1x = coords[i].x + seg;
            const cp1y = coords[i].y + m[i] * seg;
            const cp2x = coords[i + 1].x - seg;
            const cp2y = coords[i + 1].y - m[i + 1] * seg;
            path += ` C ${cp1x.toFixed(1)} ${cp1y.toFixed(1)}, ${cp2x.toFixed(1)} ${cp2y.toFixed(1)}, ${coords[i + 1].x.toFixed(1)} ${coords[i + 1].y.toFixed(1)}`;
        }

        return path;
    }

    function shouldShowPointLabel(points, index) {
        const n = points.length;

        // Day mode: 24 points, show every 4th hour
        if (n === 24) {
            return index % 4 === 0;
        }

        // Week mode: 7 points, show all
        if (n === 7) {
            return true;
        }

        // Year mode: 12 points, show all
        if (n === 12) {
            return true;
        }

        // Month mode: 28-31 points, show every 5th + first/last
        if (n >= 28 && n <= 31) {
            return index === 0 || index === n - 1 || index % 5 === 0;
        }

        // Lifetime / other: show every other
        return index % 2 === 0;
    }

    function formatNumber(value) {
        return value.toLocaleString();
    }

    function dismissTooltip(container) {
        const tip = container.querySelector('[data-analytics-tooltip]');
        if (tip) {
            tip.hidden = true;
        }
    }

    function showTooltip(container, label, count, anchorX, anchorY, svgEl) {
        let tip = container.querySelector('[data-analytics-tooltip]');
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'analytics-tooltip';
            tip.setAttribute('data-analytics-tooltip', '');
            container.appendChild(tip);
        }

        tip.textContent = `${label}: ${formatNumber(count)} check-in${count === 1 ? '' : 's'}`;
        tip.hidden = false;

        const svgRect = svgEl.getBoundingClientRect();
        const containerRect = container.getBoundingClientRect();
        const viewBox = svgEl.viewBox.baseVal;
        const scaleX = svgRect.width / viewBox.width;
        const scaleY = svgRect.height / viewBox.height;
        let px = (anchorX * scaleX) + svgRect.left - containerRect.left;
        const py = (anchorY * scaleY) + svgRect.top - containerRect.top;

        const tipWidth = tip.offsetWidth;
        const minLeft = tipWidth / 2 + 4;
        const maxLeft = containerRect.width - tipWidth / 2 - 4;
        px = Math.max(minLeft, Math.min(maxLeft, px));

        tip.style.left = `${px}px`;
        tip.style.top = `${py - 14}px`;
    }

    function renderChart(points) {
        const width = 640;
        const height = 300;
        const padding = 46;
        const maxValue = Math.max(1, ...points.map((point) => Number(point.total || 0)));
        const coords = pointCoords(points, width, height, padding, maxValue);
        const path = chartPath(coords);

        // Build area path anchored cleanly to the baseline at each end
        let areaPath = '';
        if (path && coords.length) {
            const first = coords[0];
            const last = coords[coords.length - 1];
            const baseline = height - padding;
            areaPath = `${path} L ${last.x.toFixed(1)} ${baseline} L ${first.x.toFixed(1)} ${baseline} Z`;
        }

        // Grid lines: 4 evenly spaced horizontal lines
        const gridCount = 4;
        const gridLines = [];
        for (let i = 0; i <= gridCount; i++) {
            const value = Math.round((maxValue * i) / gridCount);
            const y = height - padding - ((height - padding * 2) * (value)) / maxValue;
            if (i > 0) {
                gridLines.push(
                    `<line class="analytics-grid-line" x1="${padding}" y1="${y.toFixed(1)}" x2="${width - padding}" y2="${y.toFixed(1)}" />`
                );
            }
            gridLines.push(
                `<text class="analytics-grid-label" x="${padding - 8}" y="${(y + 4).toFixed(1)}" text-anchor="end">${formatNumber(value)}</text>`
            );
        }

        chart.innerHTML = `
            <svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Check-ins for selected period">
                <defs>
                    <linearGradient id="analytics-area-gradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--color-primary)" stop-opacity="0.2" />
                        <stop offset="100%" stop-color="var(--color-primary)" stop-opacity="0" />
                    </linearGradient>
                </defs>
                ${gridLines.join('\n                ')}
                <line class="analytics-line-axis" x1="${padding}" y1="${height - padding}" x2="${width - padding}" y2="${height - padding}"></line>
                <line class="analytics-line-axis" x1="${padding}" y1="${padding}" x2="${padding}" y2="${height - padding}"></line>
                ${areaPath ? `<path class="analytics-line-area" d="${areaPath}" fill="url(#analytics-area-gradient)"></path>` : ''}
                ${path ? `<path class="analytics-line-path analytics-line-path-animate" d="${path}"></path>` : ''}
                ${points.map((point, index) => {
                    const cx = coords[index].x;
                    const cy = coords[index].y;
                    const count = Number(point.total || 0);
                    const label = shouldShowPointLabel(points, index)
                        ? `<text class="analytics-line-label" x="${cx.toFixed(1)}" y="${height - 18}" text-anchor="middle">${escapeHtml(point.axis_label || point.label)}</text>`
                        : '';

                    return `
                        <circle class="analytics-line-point" cx="${cx.toFixed(1)}" cy="${cy.toFixed(1)}" r="5"></circle>
                        <circle class="analytics-line-hit" cx="${cx.toFixed(1)}" cy="${cy.toFixed(1)}" r="16" fill="transparent" style="cursor:pointer"
                            data-tooltip-label="${escapeHtml(point.label)}"
                            data-tooltip-count="${count}"
                            data-tooltip-x="${cx.toFixed(1)}"
                            data-tooltip-y="${cy.toFixed(1)}"></circle>
                        ${label}
                    `;
                }).join('')}
            </svg>
        `;

        // Stroke-dasharray animation for the line path
        const linePath = chart.querySelector('.analytics-line-path-animate');
        if (linePath) {
            const length = linePath.getTotalLength();
            linePath.style.strokeDasharray = length;
            linePath.style.strokeDashoffset = length;
            // Force a layout so the browser registers the initial state
            linePath.getBoundingClientRect();
            linePath.style.strokeDashoffset = '0';
        }

        // Touch-friendly tooltips via hit circles
        const svgEl = chart.querySelector('svg');
        const hitCircles = chart.querySelectorAll('.analytics-line-hit');
        hitCircles.forEach((circle) => {
            const handler = (event) => {
                event.preventDefault();
                event.stopPropagation();
                const label = circle.getAttribute('data-tooltip-label');
                const count = Number(circle.getAttribute('data-tooltip-count'));
                const ax = Number(circle.getAttribute('data-tooltip-x'));
                const ay = Number(circle.getAttribute('data-tooltip-y'));
                showTooltip(chart, label, count, ax, ay, svgEl);
            };
            circle.addEventListener('click', handler);
            circle.addEventListener('touchstart', handler, { passive: false });
        });

        // Dismiss tooltip when tapping outside
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.analytics-line-hit')) {
                dismissTooltip(chart);
            }
        });
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
            const frameStyle = String(visitor.frame_style || 'solid').replace(/[^a-z0-9_-]/gi, '') || 'solid';
            const avatarClass = `user-avatar user-avatar-small ${frame ? `has-frame-${frame} has-frame-style-${frameStyle}` : ''}`;
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

    function renderDonutChart(breakdown) {
        var donut = root.querySelector('[data-analytics-donut]');
        if (!donut) return;
        var firstTime = breakdown.first_time || 0;
        var repeat = breakdown.repeat || 0;
        var total = firstTime + repeat;
        if (total === 0) {
            donut.innerHTML = '<p class="analytics-empty">No visitor data for this period.</p>';
            return;
        }
        var firstPct = firstTime / total;
        var repeatPct = repeat / total;
        var circumference = 2 * Math.PI * 70;
        var firstLen = circumference * firstPct;
        var repeatLen = circumference * repeatPct;
        var gap = total > 0 && firstTime > 0 && repeat > 0 ? 4 : 0;

        // SVG arcs: first segment starts at top (rotated -90deg), second offset after first
        var firstDash = Math.max(0, firstLen - gap) + ' ' + (circumference - Math.max(0, firstLen - gap));
        var repeatDash = Math.max(0, repeatLen - gap) + ' ' + (circumference - Math.max(0, repeatLen - gap));
        var repeatOffset = -(firstLen + gap);

        donut.innerHTML =
            '<svg viewBox="0 0 200 200" class="analytics-donut-svg" aria-label="Visitor breakdown">' +
                '<circle cx="100" cy="100" r="70" fill="none" stroke="var(--color-primary)" stroke-width="28" ' +
                    'stroke-dasharray="' + firstDash + '" ' +
                    'transform="rotate(-90 100 100)" ' +
                    'class="analytics-donut-segment" style="stroke-dashoffset: ' + circumference + '"></circle>' +
                (repeat > 0 ?
                '<circle cx="100" cy="100" r="70" fill="none" stroke="var(--color-accent)" stroke-width="28" ' +
                    'stroke-dasharray="' + repeatDash + '" ' +
                    'stroke-dashoffset="' + repeatOffset + '" ' +
                    'transform="rotate(-90 100 100)" ' +
                    'class="analytics-donut-segment" style="stroke-dashoffset: ' + circumference + '"></circle>'
                : '') +
                '<text x="100" y="95" text-anchor="middle" class="analytics-donut-total">' + formatNumber(total) + '</text>' +
                '<text x="100" y="115" text-anchor="middle" class="analytics-donut-label">visitors</text>' +
            '</svg>' +
            '<div class="analytics-donut-legend">' +
                '<div class="analytics-donut-legend-row">' +
                    '<span class="analytics-legend-dot" style="background: var(--color-primary)"></span>' +
                    '<span>First-time</span><strong>' + formatNumber(firstTime) + '</strong>' +
                '</div>' +
                '<div class="analytics-donut-legend-row">' +
                    '<span class="analytics-legend-dot" style="background: var(--color-accent)"></span>' +
                    '<span>Repeat</span><strong>' + formatNumber(repeat) + '</strong>' +
                '</div>' +
            '</div>';

        // Animate segments on load
        requestAnimationFrame(function () {
            var segments = donut.querySelectorAll('.analytics-donut-segment');
            segments.forEach(function (seg, i) {
                if (i === 0) {
                    seg.style.strokeDashoffset = '0';
                } else {
                    seg.style.strokeDashoffset = String(repeatOffset);
                }
            });
        });
    }

    function renderHeatmap(matrix) {
        var heatmap = root.querySelector('[data-analytics-heatmap]');
        if (!heatmap) return;

        // matrix is 7 rows (Sun-Sat) x 24 cols (hours)
        if (!matrix || !matrix.length) {
            heatmap.innerHTML = '<p class="analytics-empty">No activity data yet.</p>';
            return;
        }

        var max = 0;
        matrix.forEach(function (row) {
            row.forEach(function (v) { if (v > max) max = v; });
        });
        if (max === 0) {
            heatmap.innerHTML = '<p class="analytics-empty">No activity data yet.</p>';
            return;
        }

        var dayLabels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
        var dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        var hourLabels = [];
        for (var h = 0; h < 24; h++) {
            if (h % 4 === 0) {
                var suffix = h < 12 ? 'a' : 'p';
                var display = h === 0 ? '12a' : (h <= 12 ? h + suffix : (h - 12) + suffix);
                hourLabels.push(display);
            } else {
                hourLabels.push('');
            }
        }

        var html = '<div class="analytics-heatmap-grid">';
        // Hour header row
        html += '<div class="heatmap-corner"></div>';
        for (var hi = 0; hi < 24; hi++) {
            html += '<div class="heatmap-hour-label">' + (hourLabels[hi] || '') + '</div>';
        }
        // Data rows
        for (var d = 0; d < 7; d++) {
            html += '<div class="heatmap-day-label">' + dayLabels[d] + '</div>';
            for (var hr = 0; hr < 24; hr++) {
                var val = matrix[d] ? (matrix[d][hr] || 0) : 0;
                var opacity = val / max;
                var hourStr = hr === 0 ? '12am' : (hr < 12 ? hr + 'am' : (hr === 12 ? '12pm' : (hr - 12) + 'pm'));
                var title = dayNames[d] + ' ' + hourStr + ': ' + formatNumber(val) + ' check-in' + (val === 1 ? '' : 's');
                html += '<div class="heatmap-cell" style="opacity: ' + Math.max(0.08, opacity).toFixed(2) + '" data-heatmap-label="' + escapeHtml(title) + '"></div>';
            }
        }
        html += '</div>';
        html += '<div class="heatmap-tooltip" data-heatmap-tooltip hidden></div>';
        heatmap.innerHTML = html;

        heatmap.addEventListener('click', function (e) {
            var cell = e.target.closest('.heatmap-cell');
            var tip = heatmap.querySelector('[data-heatmap-tooltip]');
            if (!cell || !tip) { if (tip) tip.hidden = true; return; }
            tip.textContent = cell.dataset.heatmapLabel;
            tip.hidden = false;
            var cellRect = cell.getBoundingClientRect();
            var mapRect = heatmap.getBoundingClientRect();
            var left = cellRect.left - mapRect.left + cellRect.width / 2;
            var top = cellRect.top - mapRect.top - 8;
            var tipWidth = tip.offsetWidth;
            left = Math.max(tipWidth / 2 + 4, Math.min(mapRect.width - tipWidth / 2 - 4, left));
            tip.style.left = left + 'px';
            tip.style.top = top + 'px';
        });
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

                // Trend indicator
                var trendBadge = widget.querySelector('[data-analytics-trend]');
                if (trendBadge && data.trend && data.trend.change_pct !== null) {
                    var pct = data.trend.change_pct;
                    var arrow = pct >= 0 ? '↑' : '↓';
                    trendBadge.textContent = arrow + ' ' + Math.abs(pct) + '%';
                    trendBadge.className = 'analytics-trend-badge ' + (pct >= 0 ? 'is-trend-up' : 'is-trend-down');
                    trendBadge.hidden = false;
                } else if (trendBadge) {
                    trendBadge.hidden = true;
                }

                renderChart(data.points || []);
                renderSummaryCards(data.summary_cards || []);
                renderVisitors(data.top_visitors || []);
                renderDonutChart(data.visitor_breakdown || { first_time: 0, repeat: 0 });
                renderHeatmap(data.activity_heatmap || []);

                // Update stat cards with summary data
                var cards = data.summary_cards || [];
                cards.forEach(function (card) {
                    if (card.label === 'Unique Visitors') {
                        var el = root.querySelector('[data-stat-unique-visitors]');
                        if (el) el.textContent = card.value;
                    }
                });
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
};
window.CraftCrawlInitBusinessAnalytics();
