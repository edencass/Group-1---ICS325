CREATE DATABASE IF NOT EXISTS scholarship_finder;

USE scholarship_finder;

-- 1 ROLES
CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
);

-- 2 USERS
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id)
        REFERENCES roles(role_id)
);

CREATE INDEX idx_users_role
ON users(role_id);

-- 3 STATES
CREATE TABLE IF NOT EXISTS states (
    state_id INT AUTO_INCREMENT PRIMARY KEY,
    state_name VARCHAR(100) NOT NULL,
    state_code CHAR(2) NOT NULL UNIQUE
);

-- 4 USER PROFILES
CREATE TABLE IF NOT EXISTS profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    grade_level VARCHAR(50),
    gpa DECIMAL(3,2),
    state_id INT,

    CONSTRAINT fk_profile_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id),

    CONSTRAINT fk_profile_state
        FOREIGN KEY (state_id)
        REFERENCES states(state_id)
);

CREATE INDEX idx_profile_user
ON profiles(user_id);

-- 5 FIELDS OF STUDY
CREATE TABLE IF NOT EXISTS fields_of_study (
    field_id INT AUTO_INCREMENT PRIMARY KEY,
    field_name VARCHAR(150) NOT NULL UNIQUE
);

-- 6 PROFILE INTERESTS
CREATE TABLE IF NOT EXISTS profile_interests (
    interest_id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id INT NOT NULL,
    field_id INT NOT NULL,

    CONSTRAINT fk_interest_profile
        FOREIGN KEY (profile_id)
        REFERENCES profiles(profile_id),

    CONSTRAINT fk_interest_field
        FOREIGN KEY (field_id)
        REFERENCES fields_of_study(field_id)
);

CREATE INDEX idx_interest_profile
ON profile_interests(profile_id);

CREATE INDEX idx_interest_field
ON profile_interests(field_id);

-- 7 PROVIDER TYPES
CREATE TABLE IF NOT EXISTS provider_type (
    provider_type_id INT AUTO_INCREMENT PRIMARY KEY,
    provider_type_name VARCHAR(100) NOT NULL UNIQUE
);

-- 8 PROVIDERS
CREATE TABLE IF NOT EXISTS providers (
    provider_id INT AUTO_INCREMENT PRIMARY KEY,
    provider_type_id INT NOT NULL,
    provider_name VARCHAR(255) NOT NULL,
    website VARCHAR(255),

    CONSTRAINT fk_provider_type
        FOREIGN KEY (provider_type_id)
        REFERENCES provider_type(provider_type_id)
);

-- 9 SCHOLARSHIPS
CREATE TABLE IF NOT EXISTS scholarships (
    scholarship_id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    state_id INT,
    state_code CHAR(2),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    discovery_source TEXT,
    amount DECIMAL(12,2),
    maximum_value DECIMAL(12,2),
    number_of_awards VARCHAR(100),
    renewable VARCHAR(20),
    deadline DATE,
    official_url VARCHAR(2048),
    scope VARCHAR(20) NOT NULL DEFAULT 'State/Local',
    is_national TINYINT(1) NOT NULL DEFAULT 0,
    open_date DATE,
    verification_date DATE,
    school_level VARCHAR(100),
    residency_requirement TEXT,
    minimum_gpa VARCHAR(50),
    financial_need_required VARCHAR(20),
    essay_required VARCHAR(20),
    recommendation_required VARCHAR(20),
    transcript_test_required VARCHAR(20),
    community_service_requirement TEXT,
    leadership_requirement VARCHAR(20),
    other_requirements TEXT,
    application_method TEXT,
    notes TEXT,
    validation_messages LONGTEXT,

    CONSTRAINT fk_scholarship_provider
        FOREIGN KEY (provider_id)
        REFERENCES providers(provider_id),

    CONSTRAINT fk_scholarship_state
        FOREIGN KEY (state_id)
        REFERENCES states(state_id)
);

CREATE INDEX idx_scholarship_provider
ON scholarships(provider_id);

CREATE INDEX idx_scholarship_state
ON scholarships(state_id);

-- 10 GRADE LEVELS
CREATE TABLE IF NOT EXISTS grade_levels (
    grade_level_id INT AUTO_INCREMENT PRIMARY KEY,
    grade_name VARCHAR(50) NOT NULL
);

-- 11 SCHOLARSHIP GRADES
CREATE TABLE IF NOT EXISTS scholarship_grades (
    scholarship_id INT NOT NULL,
    grade_level_id INT NOT NULL,

    PRIMARY KEY (scholarship_id, grade_level_id),

    CONSTRAINT fk_sg_scholarship
        FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_sg_grade
        FOREIGN KEY (grade_level_id)
        REFERENCES grade_levels(grade_level_id)
        ON DELETE CASCADE
);

-- 12 CATEGORIES
CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE
);

-- 13 SCHOLARSHIP CATEGORIES
CREATE TABLE IF NOT EXISTS scholarship_categories (
    scholarship_id INT NOT NULL,
    category_id INT NOT NULL,

    PRIMARY KEY (scholarship_id, category_id),

    CONSTRAINT fk_sc_scholarship
        FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_sc_category
        FOREIGN KEY (category_id)
        REFERENCES categories(category_id)
        ON DELETE CASCADE
);

-- 14 SCHOLARSHIP FIELDS OF STUDY
CREATE TABLE IF NOT EXISTS scholarship_fields (
    scholarship_id INT NOT NULL,
    field_id INT NOT NULL,

    PRIMARY KEY (scholarship_id, field_id),

    CONSTRAINT fk_sf_scholarship
        FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_sf_field
        FOREIGN KEY (field_id)
        REFERENCES fields_of_study(field_id)
        ON DELETE CASCADE
);

-- 14 NATIONAL THEMES
CREATE TABLE IF NOT EXISTS national_themes (
    theme_id INT AUTO_INCREMENT PRIMARY KEY,
    theme_name VARCHAR(150) NOT NULL UNIQUE
);

-- 15 SAVED SCHOLARSHIPS
CREATE TABLE IF NOT EXISTS saved_scholarships (
    saved_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    scholarship_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_saved_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id),

    CONSTRAINT fk_saved_scholarship
        FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id),

    UNIQUE(user_id, scholarship_id)
);

-- 16 IMPORT BATCHES
CREATE TABLE IF NOT EXISTS import_batches (
    batch_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    import_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50),

    CONSTRAINT fk_batch_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
);

-- 17 IMPORT STAGING ROWS
CREATE TABLE IF NOT EXISTS import_staging_rows (
    row_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    raw_data LONGTEXT,
    validation_status VARCHAR(50),

    CONSTRAINT fk_staging_batch
        FOREIGN KEY (batch_id)
        REFERENCES import_batches(batch_id)
        ON DELETE CASCADE
);

-- 18 CONTACT MESSAGES
CREATE TABLE IF NOT EXISTS contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255),
    message TEXT,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_message_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
);

-- 19 SEARCH LOG
CREATE TABLE IF NOT EXISTS search_log (
    search_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    search_term VARCHAR(255),
    searched_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_search_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
);

-- Anonymous search filter events for aggregate statistics; no user or query text is stored.
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

-- 20 AUDIT LOG
CREATE TABLE IF NOT EXISTS audit_log (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
);

CREATE INDEX idx_audit_user
ON audit_log(user_id);

CREATE INDEX idx_search_user
ON search_log(user_id);

CREATE INDEX idx_saved_user
ON saved_scholarships(user_id);

CREATE INDEX idx_saved_scholarship
ON saved_scholarships(scholarship_id);

-- 21 PARENT-STUDENT RELATIONSHIPS
CREATE TABLE IF NOT EXISTS parent_student_relationships (
    relationship_id INT AUTO_INCREMENT PRIMARY KEY,
    parent_user_id INT NOT NULL,
    student_user_id INT NOT NULL,
    relationship_type ENUM(
        'Parent',
        'Guardian',
        'Grandparent',
        'Other'
    ) DEFAULT 'Parent',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_psr_parent
        FOREIGN KEY (parent_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_psr_student
        FOREIGN KEY (student_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,

    UNIQUE KEY uq_parent_student (
        parent_user_id,
        student_user_id
    )
);
