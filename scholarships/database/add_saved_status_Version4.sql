USE scholarship_finder;

ALTER TABLE saved_scholarships
ADD COLUMN status ENUM(
    'Interested',
    'In Progress',
    'Submitted',
    'Awarded',
    'Not Awarded'
) NOT NULL DEFAULT 'Interested';