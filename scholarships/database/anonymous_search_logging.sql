-- Apply to existing scholarship_finder databases to enable anonymous search statistics.
CREATE TABLE IF NOT EXISTS anonymous_search_log (
    search_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    state_code CHAR(2) NULL,
    grade TINYINT UNSIGNED NULL,
    field_id INT UNSIGNED NULL,
    searched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_anonymous_search_state (state_code),
    INDEX idx_anonymous_search_grade (grade),
    INDEX idx_anonymous_search_field (field_id),
    INDEX idx_anonymous_search_date (searched_at)
);