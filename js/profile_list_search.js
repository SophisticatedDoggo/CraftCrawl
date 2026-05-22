window.CraftCrawlInitProfileListSearch = function (root = document) {
    root.querySelectorAll('[data-profile-filter-list]').forEach((section) => {
        if (section.dataset.profileFilterReady === 'true') {
            return;
        }
        section.dataset.profileFilterReady = 'true';

        const input = section.querySelector('[data-profile-filter-input]');
        const items = Array.from(section.querySelectorAll('[data-profile-filter-item]'));
        const empty = section.querySelector('[data-profile-filter-empty]');
        const pageSize = Math.max(0, Number(section.dataset.profilePageSize || 0));
        const pagination = section.querySelector('[data-profile-pagination]');
        const previousButton = section.querySelector('[data-profile-page-prev]');
        const nextButton = section.querySelector('[data-profile-page-next]');
        const pageStatus = section.querySelector('[data-profile-page-status]');
        let currentPage = 1;

        if (!(input instanceof HTMLInputElement) || items.length === 0) {
            input?.closest('.profile-list-search')?.setAttribute('hidden', '');
            pagination?.setAttribute('hidden', '');
            return;
        }

        const searchableItems = items.map((item) => ({
            element: item,
            text: item.textContent.toLowerCase()
        }));

        function updateFilter() {
            const query = input.value.trim().toLowerCase();
            const matchingItems = searchableItems.filter((item) => query === '' || item.text.includes(query));
            const visibleCount = matchingItems.length;
            const totalPages = pageSize > 0 ? Math.max(1, Math.ceil(visibleCount / pageSize)) : 1;

            currentPage = Math.min(currentPage, totalPages);
            const pageStart = pageSize > 0 ? (currentPage - 1) * pageSize : 0;
            const pageEnd = pageSize > 0 ? pageStart + pageSize : visibleCount;
            const pageItems = pageSize > 0 ? matchingItems.slice(pageStart, pageEnd) : matchingItems;
            const visibleElements = new Set(pageItems.map((item) => item.element));

            searchableItems.forEach((item) => {
                item.element.hidden = !visibleElements.has(item.element);
            });

            if (empty) {
                empty.hidden = query === '' || visibleCount > 0;
            }

            if (pagination && pageSize > 0) {
                pagination.hidden = visibleCount <= pageSize;
                if (pageStatus) {
                    pageStatus.textContent = visibleCount === 0
                        ? 'No results'
                        : `Page ${currentPage} of ${totalPages}`;
                }
                if (previousButton) {
                    previousButton.disabled = currentPage <= 1;
                }
                if (nextButton) {
                    nextButton.disabled = currentPage >= totalPages;
                }
            }
        }

        input.addEventListener('input', () => {
            currentPage = 1;
            updateFilter();
        });

        previousButton?.addEventListener('click', () => {
            currentPage = Math.max(1, currentPage - 1);
            updateFilter();
        });

        nextButton?.addEventListener('click', () => {
            currentPage += 1;
            updateFilter();
        });

        updateFilter();
    });
};

window.CraftCrawlInitProfileListSearch();
