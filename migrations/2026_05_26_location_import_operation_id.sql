ALTER TABLE location_import_batches
    ADD COLUMN operation_id VARCHAR(40) NULL AFTER source_provider;

CREATE INDEX idx_location_import_batches_operation
    ON location_import_batches (operation_id);
