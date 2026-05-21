window.CraftCrawlInitProfileListSearch = function (root = document) {
    root.querySelectorAll('[data-profile-filter-list]').forEach((section) => {
        if (section.dataset.profileFilterReady === 'true') {
            return;
        }
        section.dataset.profileFilterReady = 'true';

        const input = section.querySelector('[data-profile-filter-input]');
        const items = Array.from(section.querySelectorAll('[data-profile-filter-item]'));
        const empty = section.querySelector('[data-profile-filter-empty]');

        if (!(input instanceof HTMLInputElement) || items.length === 0) {
            input?.closest('.profile-list-search')?.setAttribute('hidden', '');
            return;
        }

        const searchableItems = items.map((item) => ({
            element: item,
            text: item.textContent.toLowerCase()
        }));

        function updateFilter() {
            const query = input.value.trim().toLowerCase();
            let visibleCount = 0;

            searchableItems.forEach((item) => {
                const isMatch = query === '' || item.text.includes(query);
                item.element.hidden = !isMatch;
                if (isMatch) {
                    visibleCount += 1;
                }
            });

            if (empty) {
                empty.hidden = query === '' || visibleCount > 0;
            }
        }

        input.addEventListener('input', updateFilter);
        updateFilter();
    });
};

window.CraftCrawlInitProfileListSearch();
