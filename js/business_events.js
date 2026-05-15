window.CraftCrawlInitBusinessEvents = function (root = document) {
    const recurringCheckbox = root.querySelector('#is_recurring');
    const recurrenceFields = root.querySelector('#recurrence_fields');
    if (!recurringCheckbox || !recurrenceFields || recurringCheckbox.dataset.businessEventsReady === 'true') return;
    recurringCheckbox.dataset.businessEventsReady = 'true';
    const update = () => recurrenceFields.classList.toggle('recurrence-fields-hidden', !recurringCheckbox.checked);
    update(); recurringCheckbox.addEventListener('change', update);
};
window.CraftCrawlInitBusinessEvents();
