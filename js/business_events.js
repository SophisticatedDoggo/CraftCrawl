const recurringCheckbox = document.getElementById('is_recurring');
const recurrenceFields = document.getElementById('recurrence_fields');

function updateRecurrenceFields() {
    if (!recurringCheckbox || !recurrenceFields) {
        return;
    }

    recurrenceFields.classList.toggle('recurrence-fields-hidden', !recurringCheckbox.checked);
}

updateRecurrenceFields();

if (recurringCheckbox) {
    recurringCheckbox.addEventListener('change', updateRecurrenceFields);
}
