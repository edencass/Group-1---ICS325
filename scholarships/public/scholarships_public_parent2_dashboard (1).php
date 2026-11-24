SELECT
        ss.saved_id,
        ss.saved_at,
        ss.user_id AS student_user_id,
        s.scholarship_id,
        s.title,
        s.amount,
        s.deadline,
        s.official_url,
        p.provider_name,
        prf.first_name,
        prf.last_name,
        pr.email
   FROM parent_student_relationships psr
   JOIN saved_scholarships ss
     ON ss.user_id = psr.student_user_id
   JOIN users pr
     ON pr.user_id = psr.student_user_id
   LEFT JOIN profiles prf
     ON prf.user_id = psr.student_user_id
   JOIN scholarships s
     ON s.scholarship_id = ss.scholarship_id
   JOIN providers p
     ON p.provider_id = s.provider_id
  WHERE psr.parent_user_id = ?
  ORDER BY
    CASE
      WHEN s.deadline IS NULL THEN 1
      ELSE 0
    END,
    s.deadline ASC,
    prf.first_name ASC,
    s.title ASC