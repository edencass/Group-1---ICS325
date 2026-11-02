USE scholarship_finder;

ALTER TABLE scholarships 
  ADD COLUMN official_url VARCHAR(2048) NULL AFTER deadline,
  ADD COLUMN scope VARCHAR(20) NOT NULL DEFAULT 'State/Local' AFTER official_url,
  ADD COLUMN is_national TINYINT(1) NOT NULL DEFAULT 0 AFTER scope,
  ADD COLUMN open_date DATE NULL AFTER is_national,
  ADD COLUMN verification_date DATE NULL AFTER open_date,
  ADD COLUMN validation_messages LONGTEXT NULL AFTER verification_date;