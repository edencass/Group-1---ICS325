ALTER TABLE import_batches
  ADD COLUMN contributor_student VARCHAR(255) NULL,
  ADD COLUMN imported_rows INT NULL,
  ADD COLUMN imported_at DATETIME NULL,
  ADD COLUMN error_message TEXT NULL;

CREATE TABLE IF NOT EXISTS import_history (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    source_file VARCHAR(255) NULL,
    contributor_student VARCHAR(255) NULL,
    rows_imported INT NOT NULL DEFAULT 0,
    imported_by BIGINT UNSIGNED NULL,
    imported_at DATETIME NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'completed',
    PRIMARY KEY (history_id),
    KEY idx_import_history_batch_id (batch_id),
    KEY idx_import_history_imported_at (imported_at)
);