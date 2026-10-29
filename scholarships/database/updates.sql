USE scholarship_finder;

-- Apply to existing databases created before state_code was added to scholarships.
ALTER TABLE scholarships
    ADD COLUMN state_code CHAR(2) NULL AFTER state_id,
    ADD COLUMN discovery_source TEXT NULL,
    ADD COLUMN maximum_value DECIMAL(12,2) NULL,
    ADD COLUMN number_of_awards VARCHAR(100) NULL,
    ADD COLUMN renewable VARCHAR(20) NULL,
    ADD COLUMN school_level VARCHAR(100) NULL,
    ADD COLUMN residency_requirement TEXT NULL,
    ADD COLUMN minimum_gpa VARCHAR(50) NULL,
    ADD COLUMN financial_need_required VARCHAR(20) NULL,
    ADD COLUMN essay_required VARCHAR(20) NULL,
    ADD COLUMN recommendation_required VARCHAR(20) NULL,
    ADD COLUMN transcript_test_required VARCHAR(20) NULL,
    ADD COLUMN community_service_requirement TEXT NULL,
    ADD COLUMN leadership_requirement VARCHAR(20) NULL,
    ADD COLUMN other_requirements TEXT NULL,
    ADD COLUMN application_method TEXT NULL,
    ADD COLUMN notes TEXT NULL;

UPDATE scholarships AS scholarship
JOIN states AS state
    ON state.state_id = scholarship.state_id
SET scholarship.state_code = state.state_code
WHERE scholarship.state_code IS NULL;

