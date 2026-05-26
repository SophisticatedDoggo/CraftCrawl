ALTER TABLE location_suggestions
    ADD COLUMN provider_hours_json JSON NULL AFTER user_notes;
