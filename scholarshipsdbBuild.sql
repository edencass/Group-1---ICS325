-- MySQL starter schema
CREATE DATABASE IF NOT EXISTS scholarship_db;
USE scholarship_db;

-- ROLES
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
);


-- USERS
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (role_id)
        REFERENCES roles(role_id),

    INDEX idx_users_role(role_id),
    INDEX idx_users_email(email)
);


-- STATES
CREATE TABLE states (
    state_id INT AUTO_INCREMENT PRIMARY KEY,
    state_code CHAR(2) NOT NULL UNIQUE,
    state_name VARCHAR(100) NOT NULL
);


-- GRADE LEVELS
CREATE TABLE grade_levels (
    grade_level_id INT AUTO_INCREMENT PRIMARY KEY,
    grade_name VARCHAR(50) NOT NULL UNIQUE
);


-- STUDENT PROFILES
CREATE TABLE student_profiles (
    student_profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    state_id INT,
    grade_level_id INT,

    gpa DECIMAL(3,2),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    created_by INT NULL,
    updated_by INT NULL,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id),

    FOREIGN KEY (state_id)
        REFERENCES states(state_id),

    FOREIGN KEY (grade_level_id)
        REFERENCES grade_levels(grade_level_id),

    FOREIGN KEY (created_by)
        REFERENCES users(user_id),

    FOREIGN KEY (updated_by)
        REFERENCES users(user_id),

    INDEX idx_student_user(user_id),
    INDEX idx_student_state(state_id)
);


-- PROVIDERS
CREATE TABLE providers (
    provider_id INT AUTO_INCREMENT PRIMARY KEY,
    provider_name VARCHAR(255) NOT NULL,
    website_url VARCHAR(500),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,

    FOREIGN KEY (created_by)
        REFERENCES users(user_id)
);


-- CATEGORIES
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,

    created_by INT NULL,

    FOREIGN KEY (created_by)
        REFERENCES users(user_id)
);


-- FIELDS OF STUDY
CREATE TABLE fields_of_study (
    field_of_study_id INT AUTO_INCREMENT PRIMARY KEY,
    field_name VARCHAR(150) NOT NULL UNIQUE,

    created_by INT NULL,

    FOREIGN KEY (created_by)
        REFERENCES users(user_id)
);


-- SCHOLARSHIPS
CREATE TABLE scholarships (
    scholarship_id INT AUTO_INCREMENT PRIMARY KEY,

    provider_id INT NOT NULL,
    state_id INT NULL,

    scholarship_name VARCHAR(255) NOT NULL,
    description TEXT,
    award_amount DECIMAL(10,2),
    application_deadline DATE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    created_by INT NULL,
    updated_by INT NULL,

    FOREIGN KEY (provider_id)
        REFERENCES providers(provider_id),

    FOREIGN KEY (state_id)
        REFERENCES states(state_id),

    FOREIGN KEY (created_by)
        REFERENCES users(user_id),

    FOREIGN KEY (updated_by)
        REFERENCES users(user_id),

    INDEX idx_scholarship_provider(provider_id),
    INDEX idx_scholarship_state(state_id)
);


-- SCHOLARSHIP CATEGORIES
CREATE TABLE scholarship_categories (
    scholarship_id INT NOT NULL,
    category_id INT NOT NULL,

    PRIMARY KEY (scholarship_id, category_id),

    FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id),

    FOREIGN KEY (category_id)
        REFERENCES categories(category_id)
);


-- SCHOLARSHIP GRADE LEVELS
CREATE TABLE scholarship_grades (
    scholarship_id INT NOT NULL,
    grade_level_id INT NOT NULL,

    PRIMARY KEY (scholarship_id, grade_level_id),

    FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id),

    FOREIGN KEY (grade_level_id)
        REFERENCES grade_levels(grade_level_id)
);


-- SCHOLARSHIP FIELDS OF STUDY
CREATE TABLE scholarship_fields_of_study (
    scholarship_id INT NOT NULL,
    field_of_study_id INT NOT NULL,

    PRIMARY KEY (
        scholarship_id,
        field_of_study_id
    ),

    FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id),

    FOREIGN KEY (field_of_study_id)
        REFERENCES fields_of_study(field_of_study_id)
);


-- STUDENT INTERESTS
CREATE TABLE profile_interests (
    profile_interest_id INT AUTO_INCREMENT PRIMARY KEY,
    student_profile_id INT NOT NULL,
    field_of_study_id INT NOT NULL,

    FOREIGN KEY (student_profile_id)
        REFERENCES student_profiles(student_profile_id),

    FOREIGN KEY (field_of_study_id)
        REFERENCES fields_of_study(field_of_study_id),

    UNIQUE (
        student_profile_id,
        field_of_study_id
    )
);