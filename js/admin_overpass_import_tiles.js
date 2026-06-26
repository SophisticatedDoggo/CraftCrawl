window.CraftCrawlInitOverpassImportTiles = function (root = document) {
    function formatSummary(summary = {}) {
        const pending = Number(summary.pending_review || 0);
        const review = Number(summary.review || 0);
        const reviewText = pending && pending !== review
            ? `Review ${review} (${pending} pending)`
            : `Review ${review}`;
        return `Raw ${summary.raw || 0} · Created ${summary.created || 0} · ${reviewText} · Rejected ${summary.rejected || 0} · Duplicates ${summary.duplicate || 0} · Skipped ${summary.skipped || 0} · Errors ${summary.error || 0}`;
    }

    function renderOperation(panel, operation) {
        const operationPanel = panel.querySelector('[data-overpass-current-operation]');
        const status = panel.querySelector('[data-overpass-operation-status]');
        const progress = panel.querySelector('[data-overpass-operation-progress]');
        const detail = panel.querySelector('[data-overpass-operation-detail]');
        const summary = panel.querySelector('[data-overpass-operation-summary]');
        const error = panel.querySelector('[data-overpass-operation-error]');
        const stopButton = panel.querySelector('[data-overpass-operation-stop]');

        if (!operationPanel || !status || !progress || !detail || !summary || !error) {
            return;
        }

        if (!operation) {
            operationPanel.hidden = true;
            return;
        }

        operationPanel.hidden = false;
        progress.value = operation.percent || 0;
        operationPanel.dataset.operationId = operation.operation_id || '';
        operationPanel.dataset.workerMode = operation.worker_mode || 'browser';
        const workerText = operation.worker_mode === 'background' ? 'server worker' : 'browser worker';
        status.textContent = `${operation.status} · ${operation.state} · ${operation.dry_run ? 'dry run' : 'import'} · ${workerText} · ${operation.completed_steps}/${operation.total_steps} tiles`;
        detail.textContent = operation.status === 'completed'
            ? `Completed ${operation.completed_at || ''}${operation.dry_run ? ' · Dry run totals only; no import batch rows or locations were written.' : ''}`.trim()
            : `Current: ${operation.current_tile_label || 'starting'}${operation.updated_at ? ` · Updated ${operation.updated_at}` : ''}`;
        summary.textContent = formatSummary(operation.summary);
        if (stopButton) {
            stopButton.hidden = !(operation.status === 'running' || operation.status === 'queued');
            stopButton.disabled = false;
        }
        error.hidden = !operation.api_error;
        error.textContent = operation.api_error || '';
    }

    function pollOperation(panel, endpoint, operationId, pollToken, shouldWork = false) {
        const url = new URL(endpoint, window.location.href);
        if (operationId) {
            url.searchParams.set('operation_id', operationId);
        }
        if (shouldWork && operationId) {
            url.searchParams.set('work', '1');
        }

        url.searchParams.set('_', String(Date.now()));

        window.fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then((response) => response.json())
            .then((payload) => {
                const operation = payload.operation || null;
                if (pollToken && panel.dataset.overpassOperationPollToken !== pollToken) {
                    return;
                }
                renderOperation(panel, operation);
                if (!operation) {
                    window.setTimeout(() => pollOperation(panel, endpoint, '', pollToken), 5000);
                    return;
                }

                if (operation.status === 'running' || operation.status === 'queued') {
                    const shouldWorkNext = operation.worker_mode !== 'background';
                    window.setTimeout(() => pollOperation(panel, endpoint, operation.operation_id, pollToken, shouldWorkNext), 100);
                } else {
                    window.setTimeout(() => pollOperation(panel, endpoint, '', pollToken), 5000);
                }
            })
            .catch(() => {
                if (pollToken && panel.dataset.overpassOperationPollToken !== pollToken) {
                    return;
                }
                window.setTimeout(() => pollOperation(panel, endpoint, operationId, pollToken, shouldWork), 2000);
            });
    }

    root.querySelectorAll('[data-overpass-import-tiles]').forEach((panel) => {
        if (panel.dataset.overpassImportTilesReady === 'true') return;
        panel.dataset.overpassImportTilesReady = 'true';

        const form = panel.querySelector('[data-overpass-import-operation-form]');
        const stateSelect = panel.querySelector('#overpass_state');
        const limitInput = panel.querySelector('#overpass_limit_tiles');
        const countInfo = panel.querySelector('#overpass_tile_count_info');
        const summary = panel.querySelector('#overpass_tile_preview_summary');
        const list = panel.querySelector('#overpass_tile_preview_list');
        const endpoint = form?.dataset.operationEndpoint || 'overpass_import_operation.php';
        let tileCatalog = {};
        let pollCounter = 0;

        try {
            tileCatalog = JSON.parse(panel.dataset.tileCatalog || '{}');
        } catch (_) {
            tileCatalog = {};
        }

        if (!stateSelect || !limitInput || !countInfo || !summary || !list) {
            return;
        }

        function renderTiles() {
            const state = stateSelect.value;
            const tiles = tileCatalog[state] || [];
            const tileWord = tiles.length === 1 ? 'tile' : 'tiles';
            limitInput.max = String(Math.max(1, tiles.length));
            countInfo.textContent = `${state} has ${tiles.length} import ${tileWord}, starting with priority city/metro seeds followed by a shape-filtered coarse grid. Tile limit runs from the first tile through that number.`;
            summary.textContent = `Preview ${state} tiles`;
            list.replaceChildren();

            tiles.forEach((tile, index) => {
                const article = document.createElement('article');
                article.className = 'admin-list-item';
                const content = document.createElement('div');
                const heading = document.createElement('h3');
                const details = document.createElement('p');
                const linkRow = document.createElement('p');
                const link = document.createElement('a');

                heading.textContent = `Tile ${index + 1} · ${tile.label}`;
                details.textContent = `${tile.tile_kind === 'priority_seed' ? 'Priority city/metro seed' : 'Shape-filtered coarse grid'} · Center: ${tile.latitude}, ${tile.longitude} · Radius: ${tile.radius_meters} meters`;
                link.href = `https://www.openstreetmap.org/#map=10/${tile.latitude}/${tile.longitude}`;
                link.target = '_blank';
                link.rel = 'noopener';
                link.textContent = 'Open center in OpenStreetMap';
                linkRow.appendChild(link);
                content.append(heading, details, linkRow);
                article.appendChild(content);
                list.appendChild(article);
            });
        }

        stateSelect.addEventListener('change', renderTiles);
        renderTiles();

        form?.addEventListener('submit', (event) => {
            event.preventDefault();
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;

            window.fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                body: new FormData(form)
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (!payload.ok || !payload.operation) {
                        throw new Error(payload.message || 'Overpass import could not be started.');
                    }
                    pollCounter += 1;
                    const pollToken = String(pollCounter);
                    panel.dataset.overpassOperationPollToken = pollToken;
                    renderOperation(panel, payload.operation);
                    pollOperation(panel, endpoint, payload.operation.operation_id, pollToken, true);
                })
                .catch((error) => {
                    const operationPanel = panel.querySelector('[data-overpass-current-operation]');
                    const errorMessage = panel.querySelector('[data-overpass-operation-error]');
                    if (operationPanel) operationPanel.hidden = false;
                    if (errorMessage) {
                        errorMessage.hidden = false;
                        errorMessage.textContent = error.message;
                    }
                })
                .finally(() => {
                    if (submitButton) submitButton.disabled = false;
                });
        });

        panel.querySelector('[data-overpass-operation-stop]')?.addEventListener('click', (event) => {
            const stopButton = event.currentTarget;
            const operationPanel = panel.querySelector('[data-overpass-current-operation]');
            const operationId = operationPanel?.dataset.operationId || '';
            if (!operationId) {
                return;
            }
            stopButton.disabled = true;

            const formData = new FormData();
            formData.set('form_action', 'stop_overpass_import');
            formData.set('operation_id', operationId);
            const csrf = form?.querySelector('input[name="csrf_token"]');
            if (csrf) {
                formData.set('csrf_token', csrf.value);
            }

            window.fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (!payload.ok || !payload.operation) {
                        throw new Error(payload.message || 'Overpass import could not be stopped.');
                    }
                    pollCounter += 1;
                    const pollToken = String(pollCounter);
                    panel.dataset.overpassOperationPollToken = pollToken;
                    renderOperation(panel, payload.operation);
                })
                .catch((error) => {
                    stopButton.disabled = false;
                    const errorMessage = panel.querySelector('[data-overpass-operation-error]');
                    if (errorMessage) {
                        errorMessage.hidden = false;
                        errorMessage.textContent = error.message;
                    }
                });
        });

        pollCounter += 1;
        const pollToken = String(pollCounter);
        panel.dataset.overpassOperationPollToken = pollToken;
        pollOperation(panel, endpoint, '', pollToken);
    });
};

window.CraftCrawlInitOverpassImportTiles();
