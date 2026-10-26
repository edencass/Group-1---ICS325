DROP DATABASE IF EXISTS scholarship_finder;
CREATE DATABASE scholarship_finder;
USE scholarship_finder;

-- =====================================================
-- CREATE TABLES
-- =====================================================
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

CREATE TABLE states (
    state_id INT AUTO_INCREMENT PRIMARY KEY,
    state_name VARCHAR(100) NOT NULL,
    state_code CHAR(2) NOT NULL UNIQUE
);

CREATE TABLE profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    grade_level VARCHAR(50),
    gpa DECIMAL(3,2),
    state_id INT,
    CONSTRAINT fk_profile_user FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_profile_state FOREIGN KEY (state_id) REFERENCES states(state_id)
);

CREATE TABLE fields_of_study (
    field_id INT AUTO_INCREMENT PRIMARY KEY,
    field_name VARCHAR(150) NOT NULL UNIQUE
);

CREATE TABLE profile_interests (
    interest_id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id INT NOT NULL,
    field_id INT NOT NULL,
    CONSTRAINT fk_interest_profile FOREIGN KEY (profile_id) REFERENCES profiles(profile_id),
    CONSTRAINT fk_interest_field FOREIGN KEY (field_id) REFERENCES fields_of_study(field_id)
);

CREATE TABLE provider_type (
    provider_type_id INT AUTO_INCREMENT PRIMARY KEY,
    provider_type_name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE providers (
    provider_id INT AUTO_INCREMENT PRIMARY KEY,
    provider_type_id INT NOT NULL,
    provider_name VARCHAR(255) NOT NULL,
    website VARCHAR(255),
    CONSTRAINT fk_provider_type FOREIGN KEY (provider_type_id) REFERENCES provider_type(provider_type_id)
);

CREATE TABLE scholarships (
    scholarship_id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    state_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    amount DECIMAL(12,2),
    deadline DATE,
    official_url VARCHAR(2048),
    scope VARCHAR(20) NOT NULL DEFAULT 'State/Local',
    is_national TINYINT(1) NOT NULL DEFAULT 0,
    open_date DATE,
    verification_date DATE,
    validation_messages LONGTEXT,
    CONSTRAINT fk_scholarship_provider FOREIGN KEY (provider_id) REFERENCES providers(provider_id),
    CONSTRAINT fk_scholarship_state FOREIGN KEY (state_id) REFERENCES states(state_id)
);

CREATE TABLE grade_levels (
    grade_level_id INT AUTO_INCREMENT PRIMARY KEY,
    grade_name VARCHAR(50) NOT NULL
);

CREATE TABLE scholarship_grades (
    scholarship_id INT NOT NULL,
    grade_level_id INT NOT NULL,
    PRIMARY KEY (scholarship_id, grade_level_id),
    CONSTRAINT fk_sg_scholarship FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
    CONSTRAINT fk_sg_grade FOREIGN KEY (grade_level_id) REFERENCES grade_levels(grade_level_id) ON DELETE CASCADE
);

CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE scholarship_categories (
    scholarship_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (scholarship_id, category_id),
    CONSTRAINT fk_sc_scholarship FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
    CONSTRAINT fk_sc_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE
);

CREATE TABLE scholarship_fields (
    scholarship_id INT NOT NULL,
    field_id INT NOT NULL,
    PRIMARY KEY (scholarship_id, field_id),
    CONSTRAINT fk_sf_scholarship FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
    CONSTRAINT fk_sf_field FOREIGN KEY (field_id) REFERENCES fields_of_study(field_id) ON DELETE CASCADE
);

CREATE TABLE national_themes (
    theme_id INT AUTO_INCREMENT PRIMARY KEY,
    theme_name VARCHAR(150) NOT NULL UNIQUE
);

CREATE TABLE saved_scholarships (
    saved_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    scholarship_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_saved_user FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_saved_scholarship FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id),
    UNIQUE(user_id, scholarship_id)
);

CREATE TABLE import_batches (
    batch_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    import_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50),
    CONSTRAINT fk_batch_user FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE import_staging_rows (
    row_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    raw_data LONGTEXT,
    validation_status VARCHAR(50),
    CONSTRAINT fk_staging_batch FOREIGN KEY (batch_id) REFERENCES import_batches(batch_id) ON DELETE CASCADE
);

CREATE TABLE contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255),
    message TEXT,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_message_user FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE search_log (
    search_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    search_term VARCHAR(255),
    searched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_search_user FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE audit_log (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_profile_user ON profiles(user_id);
CREATE INDEX idx_interest_profile ON profile_interests(profile_id);
CREATE INDEX idx_interest_field ON profile_interests(field_id);
CREATE INDEX idx_scholarship_provider ON scholarships(provider_id);
CREATE INDEX idx_scholarship_state ON scholarships(state_id);
CREATE INDEX idx_audit_user ON audit_log(user_id);
CREATE INDEX idx_search_user ON search_log(user_id);
CREATE INDEX idx_saved_user ON saved_scholarships(user_id);
CREATE INDEX idx_saved_scholarship ON saved_scholarships(scholarship_id);

CREATE TABLE parent_student_relationships (
    relationship_id INT AUTO_INCREMENT PRIMARY KEY,
    parent_user_id INT NOT NULL,
    student_user_id INT NOT NULL,
    relationship_type ENUM('Parent','Guardian','Grandparent','Other') DEFAULT 'Parent',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_psr_parent FOREIGN KEY (parent_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_psr_student FOREIGN KEY (student_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uq_parent_student (parent_user_id, student_user_id)
);

-- =====================================================
-- INSERT REFERENCE DATA
-- =====================================================
INSERT INTO states (state_name, state_code) VALUES
('Alabama','AL'),
('Alaska','AK'),
('Arizona','AZ'),
('Arkansas','AR'),
('California','CA'),
('Colorado','CO'),
('Connecticut','CT'),
('Delaware','DE'),
('Florida','FL'),
('Georgia','GA'),
('Hawaii','HI'),
('Idaho','ID'),
('Illinois','IL'),
('Indiana','IN'),
('Iowa','IA'),
('Kansas','KS'),
('Kentucky','KY'),
('Louisiana','LA'),
('Maine','ME'),
('Maryland','MD'),
('Massachusetts','MA'),
('Michigan','MI'),
('Minnesota','MN'),
('Mississippi','MS'),
('Missouri','MO'),
('Montana','MT'),
('Nebraska','NE'),
('Nevada','NV'),
('New Hampshire','NH'),
('New Jersey','NJ'),
('New Mexico','NM'),
('New York','NY'),
('North Carolina','NC'),
('North Dakota','ND'),
('Ohio','OH'),
('Oklahoma','OK'),
('Oregon','OR'),
('Pennsylvania','PA'),
('Rhode Island','RI'),
('South Carolina','SC'),
('South Dakota','SD'),
('Tennessee','TN'),
('Texas','TX'),
('Utah','UT'),
('Vermont','VT'),
('Virginia','VA'),
('Washington','WA'),
('West Virginia','WV'),
('Wisconsin','WI'),
('Wyoming','WY');

INSERT INTO roles (role_name) VALUES
('Admin'),
('Parent'),
('Student'),
('Super-Admin'),
('Visitor');

INSERT INTO users (role_id, email, password_hash) VALUES
((SELECT role_id FROM roles WHERE role_name='Admin'),
 'admin@scholarships.local',
 '$2y$10$LIwn0vGjIstNy82yPHPIVu5K2Ifugun.YszhfJO9hzqiIpuSa7PBK');

INSERT INTO national_themes (theme_name) VALUES
('Arts, music, and design'),
('Asian American and Pacific Islander heritage'),
('Black/African American heritage'),
('Business, finance, and accounting'),
('Community college, transfer, graduate, and adult/returning students'),
('Community service and volunteering'),
('Computer science, data science, and IT'),
('Corporate-sponsored programs'),
('Education and teaching'),
('Engineering'),
('Environment, agriculture, and natural resources'),
('First-generation college students'),
('Foster youth and independent students'),
('Health, nursing, and pre-med'),
('Hispanic/Latino heritage'),
('Humanities and social sciences'),
('Law, public service, and government'),
('Leadership and civic engagement'),
('LGBTQ+ students'),
('Military, veterans, and military families'),
('Native American and Indigenous'),
('Professional associations'),
('Religious and faith-based'),
('Rural and small-town students'),
('Skilled trades and technical programs'),
('STEM general (math, physics, chemistry, biology)'),
('Students with disabilities'),
('Women-focused'),
('Writing, journalism, and communications');

INSERT INTO categories (category_name) VALUES
('Merit-Based'),
('Need-Based'),
('Academic'),
('Athletic'),
('STEM'),
('Community Service'),
('Leadership'),
('Career-Specific'),
('Minority Student'),
('First-Generation Student'),
('Trade School'),
('State Resident'),
('Essay Contest');

INSERT INTO grade_levels (grade_name) VALUES
('Grade 6'),
('Grade 7'),
('Grade 8'),
('Grade 9'),
('Grade 10'),
('Grade 11'),
('Grade 12');

INSERT INTO provider_type (provider_type_name) VALUES
('Government agency'),
('Foundation/nonprofit'),
('Professional association'),
('Corporate sponsor'),
('Community foundation'),
('Military organization'),
('Religious organization'),
('Labor union'),
('Education platform');

INSERT INTO fields_of_study (field_name) VALUES
('Agriculture'),
('Architecture'),
('Art'),
('Biology'),
('Business'),
('Chemistry'),
('Communications'),
('Computer Science'),
('Criminal Justice'),
('Culinary Arts'),
('Design'),
('Economics'),
('Education'),
('Engineering'),
('English'),
('Environmental Science'),
('Finance'),
('Graphic Design'),
('Health Sciences'),
('History'),
('International Relations'),
('Journalism'),
('Law'),
('Marketing'),
('Mathematics'),
('Music'),
('Nursing'),
('Pharmacy'),
('Physics'),
('Political Science'),
('Psychology'),
('Public Health'),
('Social Work'),
('Sociology'),
('Software Engineering'),
('Statistics'),
('Theater'),
('Veterinary Medicine');

-- =====================================================
-- INSERT PROVIDERS
-- =====================================================
INSERT INTO providers (provider_type_id, provider_name, website) VALUES
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Foundation/nonprofit'), 'Coca-Cola Scholars Foundation', 'https://www.coca-colascholarsfoundation.org/apply/'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Foundation/nonprofit'), 'Bill & Melinda Gates Foundation', 'https://www.thegatesscholarship.org/scholarship'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Foundation/nonprofit'), 'Dell Scholars', 'https://www.dellscholars.org/scholarship/'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Corporate sponsor'), 'Burger King Scholars Program', 'https://burgerking.scholarsapply.org/'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Foundation/nonprofit'), 'National Merit Scholarship Corporation', 'https://www.nationalmerit.org/'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Foundation/nonprofit'), 'QuestBridge', 'https://www.questbridge.org/high-school-students/national-college-match'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Foundation/nonprofit'), 'Horatio Alger Association', 'https://scholarships.horatioalger.org/'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Foundation/nonprofit'), 'Ron Brown Scholar Program', 'https://ronbrown.org/'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Foundation/nonprofit'), 'Jack Kent Cooke Foundation', 'https://www.jkcf.org/our-scholarships/college-scholarship-program/'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Religious organization'), 'Benevolent and Protective Order of Elks', 'https://www.elks.org/scholars/mvs.cfm'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Foundation/nonprofit'), 'Davidson Institute', 'https://www.davidsongifted.org/gifted-state-policy/davidson-fellows/'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Corporate sponsor'), 'AXA Achievement', 'https://www.axa-achievement.com/'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Corporate sponsor'), 'Bank of America', 'https://about.bankofamerica.com/en-us/what-guides-us/student-leaders.html'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Foundation/nonprofit'), 'College Board', 'https://bigfuture.collegeboard.org/pay-for-college/bigfuture-scholarships'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Education platform'), 'Niche', 'https://www.niche.com/colleges/scholarships'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Corporate sponsor'), 'Be Bold', 'https://www.niche.com/colleges/scholarships/be-bold-no-essay-scholarship/'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Government agency'), 'Florida Department of Education', 'https://www.floridastudentfinancialaidsg.org/'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Government agency'), 'Georgia Student Finance Commission', 'https://www.gafutures.org/'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Government agency'), 'Tennessee Higher Education Commission', 'https://www.tn.gov/tnpromise.html'),
((SELECT provider_type_id FROM provider_type WHERE provider_type_name = 'Government agency'), 'Texas Higher Education Coordinating Board', 'https://www.hhloans.com/texas-grants');

-- =====================================================
-- INSERT SCHOLARSHIPS
-- =====================================================
INSERT INTO scholarships (
    provider_id,
    state_id,
    title,
    description,
    amount,
    deadline,
    official_url,
    scope,
    is_national,
    open_date,
    verification_date,
    validation_messages
)
VALUES
((SELECT provider_id FROM providers WHERE provider_name='Coca-Cola Scholars Foundation'), NULL, 'Coca-Cola Scholars Program', 'Prestigious national leadership scholarship.', 20000.00, NULL, 'https://www.coca-colascholarsfoundation.org/apply/', 'National', 1, NULL, NULL, 'Prestigious national leadership scholarship.'),
((SELECT provider_id FROM providers WHERE provider_name='Bill & Melinda Gates Foundation'), NULL, 'The Gates Scholarship', 'Highly selective full-ride scholarship for eligible low-income seniors.', NULL, NULL, 'https://www.thegatesscholarship.org/scholarship', 'National', 1, NULL, NULL, 'Highly selective; full-ride for eligible low-income seniors.'),
((SELECT provider_id FROM providers WHERE provider_name='Dell Scholars'), NULL, 'Dell Scholars Program', 'Supports students with demonstrated need and resilience.', 20000.00, NULL, 'https://www.dellscholars.org/scholarship/', 'National', 1, NULL, NULL, 'Supports students with demonstrated need and resilience.'),
((SELECT provider_id FROM providers WHERE provider_name='Burger King Scholars Program'), NULL, 'Burger King Scholarship', 'Multiple scholarship tracks each year.', NULL, NULL, 'https://burgerking.scholarsapply.org/', 'National', 1, NULL, NULL, 'Multiple scholarship tracks each year.'),
((SELECT provider_id FROM providers WHERE provider_name='National Merit Scholarship Corporation'), NULL, 'National Merit Scholarship Corporation', 'Merit-based scholarship awarded based on test scores and academic achievement.', NULL, NULL, 'https://www.nationalmerit.org/', 'National', 1, NULL, NULL, 'Merit-based scholarship awarded based on test scores and academic achievement.'),
((SELECT provider_id FROM providers WHERE provider_name='QuestBridge'), NULL, 'QuestBridge National College Match', 'For high-achieving low-income students.', NULL, NULL, 'https://www.questbridge.org/high-school-students/national-college-match', 'National', 1, NULL, NULL, 'For high-achieving low-income students.'),
((SELECT provider_id FROM providers WHERE provider_name='Horatio Alger Association'), NULL, 'Horatio Alger Scholarship', 'For students facing adversity and demonstrating perseverance.', 25000.00, NULL, 'https://scholarships.horatioalger.org/', 'National', 1, NULL, NULL, 'For students facing adversity and demonstrating perseverance.'),
((SELECT provider_id FROM providers WHERE provider_name='Ron Brown Scholar Program'), NULL, 'Ron Brown Scholar Program', 'Leadership and community impact-focused scholarship.', 40000.00, NULL, 'https://ronbrown.org/', 'National', 1, NULL, NULL, 'Leadership and community impact-focused scholarship.'),
((SELECT provider_id FROM providers WHERE provider_name='Jack Kent Cooke Foundation'), NULL, 'Jack Kent Cooke College Scholarship', 'High-achieving students with financial need.', NULL, NULL, 'https://www.jkcf.org/our-scholarships/college-scholarship-program/', 'National', 1, NULL, NULL, 'High-achieving students with financial need.'),
((SELECT provider_id FROM providers WHERE provider_name='Benevolent and Protective Order of Elks'), NULL, 'Elks Most Valuable Student Scholarship', 'One of the largest scholarship contests for high school seniors.', 7500.00, NULL, 'https://www.elks.org/scholars/mvs.cfm', 'National', 1, NULL, NULL, 'One of the largest scholarship contests for high school seniors.'),
((SELECT provider_id FROM providers WHERE provider_name='Davidson Institute'), NULL, 'Davidson Fellows Scholarship', 'For exceptional students with significant achievements.', 50000.00, NULL, 'https://www.davidsongifted.org/gifted-state-policy/davidson-fellows/', 'National', 1, NULL, NULL, 'For exceptional students with significant achievements.'),
((SELECT provider_id FROM providers WHERE provider_name='AXA Achievement'), NULL, 'AXA Achievement Scholarship', 'Recognizes achievement, leadership and community service.', 25000.00, NULL, 'https://www.axa-achievement.com/', 'National', 1, NULL, NULL, 'Recognizes achievement, leadership and community service.'),
((SELECT provider_id FROM providers WHERE provider_name='Bank of America'), NULL, 'Bank of America Student Leaders', 'Leadership development and civic leadership program.', NULL, NULL, 'https://about.bankofamerica.com/en-us/what-guides-us/student-leaders.html', 'National', 1, NULL, NULL, 'Leadership development and civic leadership program.'),
((SELECT provider_id FROM providers WHERE provider_name='College Board'), NULL, 'FASFA / College Board BigFuture Scholarship', 'Available by completing college planning steps.', NULL, NULL, 'https://bigfuture.collegeboard.org/pay-for-college/bigfuture-scholarships', 'National', 1, NULL, NULL, 'Available by completing college planning steps.'),
((SELECT provider_id FROM providers WHERE provider_name='Niche'), NULL, 'Niche $50 000 No Essay Scholarship', 'No-essay scholarship; easy application.', 50000.00, '2026-12-31', 'https://www.niche.com/colleges/scholarships/50k-no-essay-scholarship-2026', 'National', 1, NULL, NULL, 'No-essay scholarship; easy application.'),
((SELECT provider_id FROM providers WHERE provider_name='Niche'), NULL, 'Niche $40 000 No Essay Scholarship', 'No-essay scholarship opportunity.', 40000.00, '2026-10-15', 'https://www.niche.com/colleges/scholarships/40k-no-essay-scholarship-2026', 'National', 1, NULL, NULL, 'No-essay scholarship.'),
((SELECT provider_id FROM providers WHERE provider_name='Be Bold'), NULL, 'Be Bold No-Essay Scholarship', 'Easy no-essay award.', 25000.00, '2026-10-31', 'https://www.niche.com/colleges/scholarships/be-bold-no-essay-scholarship/', 'National', 1, NULL, NULL, 'Easy no-essay award.'),
((SELECT provider_id FROM providers WHERE provider_name='Florida Department of Education'), (SELECT state_id FROM states WHERE state_code='FL'), 'Florida Bright Futures Scholarship', 'State scholarship program for Florida residents.', NULL, NULL, 'https://www.floridastudentfinancialaidsg.org/', 'State/Local', 0, NULL, NULL, 'State scholarship program for Florida residents.'),
((SELECT provider_id FROM providers WHERE provider_name='Georgia Student Finance Commission'), (SELECT state_id FROM states WHERE state_code='GA'), 'Georgia HOPE Scholarship', 'Georgia state scholarship for eligible students.', NULL, NULL, 'https://www.gafutures.org/', 'State/Local', 0, NULL, NULL, 'Georgia state scholarship for eligible students.'),
((SELECT provider_id FROM providers WHERE provider_name='Tennessee Higher Education Commission'), (SELECT state_id FROM states WHERE state_code='TN'), 'Tennessee Promise Scholarship', 'State program for Tennessee students attending eligible institutions.', NULL, NULL, 'https://www.tn.gov/tnpromise.html', 'State/Local', 0, NULL, NULL, 'State program for Tennessee students attending eligible institutions.'),
((SELECT provider_id FROM providers WHERE provider_name='Texas Higher Education Coordinating Board'), (SELECT state_id FROM states WHERE state_code='TX'), 'Texas Grant (Toward Excellence, Access and Success)', 'Need-based grant for Texas students attending eligible schools.', NULL, NULL, 'https://www.hhloans.com/texas-grants', 'State/Local', 0, NULL, NULL, 'Need-based grant for Texas students attending eligible schools.');

-- =====================================================
-- ASSIGN GRADE LEVELS
-- =====================================================
INSERT INTO scholarship_grades (scholarship_id, grade_level_id)
SELECT s.scholarship_id, gl.grade_level_id
FROM scholarships s
JOIN grade_levels gl ON gl.grade_name = 'Grade 12'
WHERE s.title IN (
    'Coca-Cola Scholars Program',
    'The Gates Scholarship',
    'Dell Scholars Program',
    'Burger King Scholarship',
    'National Merit Scholarship Corporation',
    'QuestBridge National College Match',
    'Horatio Alger Scholarship',
    'Ron Brown Scholar Program',
    'Jack Kent Cooke College Scholarship',
    'Elks Most Valuable Student Scholarship',
    'Davidson Fellows Scholarship',
    'AXA Achievement Scholarship',
    'Bank of America Student Leaders',
    'FASFA / College Board BigFuture Scholarship',
    'Niche $50 000 No Essay Scholarship',
    'Niche $40 000 No Essay Scholarship',
    'Be Bold No-Essay Scholarship',
    'Florida Bright Futures Scholarship',
    'Georgia HOPE Scholarship',
    'Tennessee Promise Scholarship',
    'Texas Grant (Toward Excellence, Access and Success)'
);

SELECT 'Database reset and scholarship seed loaded successfully.' AS status;
