-- Add 'overpass' to source_provider ENUMs and create overpass_place_imports table

ALTER TABLE locations
    MODIFY COLUMN source_provider ENUM('manual', 'mapbox', 'google', 'overpass', 'user_suggested') NOT NULL DEFAULT 'manual';

ALTER TABLE location_import_operations
    MODIFY COLUMN source_provider ENUM('google', 'overpass') NOT NULL DEFAULT 'google';

ALTER TABLE location_import_batches
    MODIFY COLUMN source_provider ENUM('google', 'overpass') NOT NULL DEFAULT 'google',
    MODIFY COLUMN google_search_mode ENUM('nearby', 'text', 'overpass') NOT NULL;

CREATE TABLE IF NOT EXISTS overpass_place_imports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT,
    location_id INT,
    osm_type ENUM('node', 'way', 'relation') NOT NULL,
    osm_id BIGINT NOT NULL,
    source_place_id VARCHAR(255) NOT NULL,
    state CHAR(2),
    osm_amenity VARCHAR(100),
    osm_craft VARCHAR(100),
    osm_tags JSON,
    raw_element_json JSON,
    fit_score INT NOT NULL DEFAULT 0,
    suggested_category VARCHAR(100),
    decision ENUM('auto_add', 'needs_review', 'reject', 'duplicate', 'error') NOT NULL,
    positive_signals JSON,
    negative_signals JSON,
    decision_reason VARCHAR(1024),
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_overpass_place_import (source_place_id),
    KEY idx_overpass_place_imports_batch (batch_id),
    KEY idx_overpass_place_imports_location (location_id),
    KEY idx_overpass_place_imports_decision (decision),
    KEY idx_overpass_place_imports_score (fit_score),
    KEY idx_overpass_place_imports_osm (osm_type, osm_id),
    CONSTRAINT fk_overpass_place_import_batchId FOREIGN KEY (batch_id)
    REFERENCES location_import_batches(id),
    CONSTRAINT fk_overpass_place_import_locationId FOREIGN KEY (location_id)
    REFERENCES locations(id)
);
