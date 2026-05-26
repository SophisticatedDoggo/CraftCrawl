CREATE TABLE IF NOT EXISTS chain_exclusion_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pattern VARCHAR(100) NOT NULL,
    reason VARCHAR(255),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_chain_exclusion_pattern (pattern),
    KEY idx_chain_exclusion_patterns_active (is_active)
);

INSERT IGNORE INTO chain_exclusion_patterns (pattern, reason) VALUES
('applebee', 'Generic chain restaurant'),
('buffalo wild wings', 'Generic chain restaurant'),
('bdubs', 'Generic chain restaurant'),
('chili''s', 'Generic chain restaurant'),
('chilis', 'Generic chain restaurant'),
('tgi fridays', 'Generic chain restaurant'),
('friday''s', 'Generic chain restaurant'),
('olive garden', 'Generic chain restaurant'),
('red lobster', 'Generic chain restaurant'),
('outback steakhouse', 'Generic chain restaurant'),
('texas roadhouse', 'Generic chain restaurant'),
('longhorn steakhouse', 'Generic chain restaurant'),
('hooters', 'Generic chain restaurant'),
('wingstop', 'Generic chain restaurant'),
('primanti', 'Generic chain restaurant'),
('ruby tuesday', 'Generic chain restaurant'),
('cheesecake factory', 'Generic chain restaurant'),
('yard house', 'Generic chain restaurant'),
('dave & buster', 'Generic entertainment chain');

CREATE TABLE IF NOT EXISTS location_import_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_provider ENUM('google') NOT NULL DEFAULT 'google',
    import_scope ENUM('state', 'all_states', 'manual') NOT NULL DEFAULT 'state',
    state CHAR(2),
    search_term VARCHAR(100) NOT NULL,
    google_search_mode ENUM('nearby', 'text') NOT NULL,
    tile_label VARCHAR(100),
    tile_center_latitude DECIMAL(9,6),
    tile_center_longitude DECIMAL(9,6),
    tile_radius_meters INT,
    status ENUM('running', 'completed', 'failed') NOT NULL DEFAULT 'running',
    raw_result_count INT NOT NULL DEFAULT 0,
    created_count INT NOT NULL DEFAULT 0,
    review_count INT NOT NULL DEFAULT 0,
    rejected_count INT NOT NULL DEFAULT 0,
    duplicate_count INT NOT NULL DEFAULT 0,
    error_count INT NOT NULL DEFAULT 0,
    api_error TEXT,
    startedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completedAt DATETIME,
    KEY idx_location_import_batches_status (status),
    KEY idx_location_import_batches_state (state),
    KEY idx_location_import_batches_started (startedAt)
);

CREATE TABLE IF NOT EXISTS google_place_imports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT,
    location_id INT,
    source_place_id VARCHAR(255) NOT NULL,
    state CHAR(2),
    search_term VARCHAR(100),
    google_primary_type VARCHAR(100),
    google_types JSON,
    raw_place_json JSON,
    fit_score INT NOT NULL DEFAULT 0,
    suggested_category VARCHAR(100),
    decision ENUM('auto_add', 'needs_review', 'reject', 'duplicate', 'error') NOT NULL,
    positive_signals JSON,
    negative_signals JSON,
    decision_reason VARCHAR(1024),
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_google_place_import (source_place_id),
    KEY idx_google_place_imports_batch (batch_id),
    KEY idx_google_place_imports_location (location_id),
    KEY idx_google_place_imports_decision (decision),
    KEY idx_google_place_imports_score (fit_score),
    CONSTRAINT fk_google_place_import_batchId FOREIGN KEY (batch_id)
    REFERENCES location_import_batches(id),
    CONSTRAINT fk_google_place_import_locationId FOREIGN KEY (location_id)
    REFERENCES locations(id)
);
