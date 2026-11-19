USE scholarship_finder;

-- 1. Insert Roles
INSERT INTO roles (role_name)
VALUES
('Visitor'),
('Student'),
('Parent'),
('Administrator'),
('Super-admin');

-- 2. Seed the Administrator account (password is admin123)
INSERT INTO users (role_id, email, password_hash, is_active)
VALUES (
    (SELECT role_id FROM roles WHERE role_name = 'Administrator'),
    'admin@scholarships.local',
    '$2y$10$bXZyKDz9B0O7NAyMvWA9vupRRHA.f4O8142ofyThNAloUq0g0Dqim',  -- Generate a hash for 'admin123' <--- this is the password
    1
);

-- 3. Seed the Super-admin account (password is superadmin123)
INSERT INTO users (role_id, email, password_hash, is_active)
VALUES (
    (SELECT role_id FROM roles WHERE role_name = 'Super-admin'),
    'superadmin@scholarships.local',
    '$2y$10$YTcjG2FgZXIrLyBLl2jtOe/ZBjOEfotgLy5jXoJYtDl.u6yoN.5Km', -- Generate a hash for 'superadmin123'  <--- this is the password
    1
);

-- 4. Insert States
INSERT INTO states (state_name, state_code)
VALUES
('Alabama', 'AL'),('Alaska', 'AK'),('Arizona', 'AZ'),('Arkansas', 'AR'),
('California', 'CA'),('Colorado', 'CO'),('Connecticut', 'CT'),('Delaware', 'DE'),
('Florida', 'FL'),('Georgia', 'GA'),('Hawaii', 'HI'),('Idaho', 'ID'),
('Illinois', 'IL'),('Indiana', 'IN'),('Iowa', 'IA'),('Kansas', 'KS'),
('Kentucky', 'KY'),('Louisiana', 'LA'),('Maine', 'ME'),('Maryland', 'MD'),
('Massachusetts', 'MA'),('Michigan', 'MI'),('Minnesota', 'MN'),('Mississippi', 'MS'),
('Missouri', 'MO'),('Montana', 'MT'),('Nebraska', 'NE'),('Nevada', 'NV'),
('New Hampshire', 'NH'),('New Jersey', 'NJ'),('New Mexico', 'NM'),('New York', 'NY'),
('North Carolina', 'NC'),('North Dakota', 'ND'),('Ohio', 'OH'),('Oklahoma', 'OK'),
('Oregon', 'OR'),('Pennsylvania', 'PA'),('Rhode Island', 'RI'),('South Carolina', 'SC'),
('South Dakota', 'SD'),('Tennessee', 'TN'),('Texas', 'TX'),('Utah', 'UT'),
('Vermont', 'VT'),('Virginia', 'VA'),('Washington', 'WA'),('West Virginia', 'WV'),
('Wisconsin', 'WI'),('Wyoming', 'WY'),('National', 'NA');

-- 5. Insert National Themes
INSERT INTO national_themes (theme_name)
VALUES
('Arts, music, and design'),('Asian American and Pacific Islander heritage'),
('Black/African American heritage'),('Business, finance, and accounting'),
('Community college, transfer, graduate, and adult/returning students'),
('Community service and volunteering'),('Computer science, data science, and IT'),
('Corporate-sponsored programs'),('Education and teaching'),('Engineering'),
('Environment, agriculture, and natural resources'),('First-generation college students'),
('Foster youth and independent students'),('Health, nursing, and pre-med'),
('Hispanic/Latino heritage'),('Humanities and social sciences'),
('Law, public service, and government'),('Leadership and civic engagement'),
('LGBTQ+ students'),('Military, veterans, and military families'),
('Native American and Indigenous'),('Professional associations'),
('Religious and faith-based'),('Rural and small-town students'),
('Skilled trades and technical programs'),('STEM general (math, physics, chemistry, biology)'),
('Students with disabilities'),('Women-focused'),('Writing, journalism, and communications');

-- 6. Insert Categories
INSERT INTO categories (category_name)
VALUES
('Merit-Based'),('Need-Based'),('Academic'),('Athletic'),('STEM'),
('Community Service'),('Leadership'),('Career-Specific'),('Minority Student'),
('First-Generation Student'),('Trade School'),('State Resident'),('Essay Contest');

-- 7. Insert Grade Levels
INSERT INTO grade_levels (grade_name)
VALUES
('Grade 6'),('Grade 7'),('Grade 8'),('Grade 9'),('Grade 10'),('Grade 11'),('Grade 12');

-- 8. Insert Provider Types
INSERT INTO provider_type (provider_type_name)
VALUES
('Government agency'),('Foundation/nonprofit'),('Professional association'),
('Corporate sponsor'),('Community foundation'),('Military organization'),
('Religious organization'),('Labor union');

-- 9. Insert Fields of Study
INSERT INTO fields_of_study (field_name)
VALUES
('Agriculture'),('Architecture'),('Art'),('Biology'),('Business'),('Chemistry'),
('Communications'),('Computer Science'),('Criminal Justice'),('Culinary Arts'),
('Design'),('Economics'),('Education'),('Engineering'),('English'),
('Environmental Science'),('Finance'),('Graphic Design'),('Health Sciences'),
('History'),('International Relations'),('Journalism'),('Law'),('Marketing'),
('Mathematics'),('Music'),('Nursing'),('Pharmacy'),('Physics'),
('Political Science'),('Psychology'),('Public Health'),('Social Work'),
('Sociology'),('Software Engineering'),('Statistics'),('Theater'),
('Veterinary Medicine');
