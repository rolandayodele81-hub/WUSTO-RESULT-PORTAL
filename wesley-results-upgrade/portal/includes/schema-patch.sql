-- Run this once against your existing wesley_portal database.
-- It makes bulk result uploads safe to re-run (upsert instead of
-- duplicating rows) and speeds up the read path students hit.

ALTER TABLE results
  ADD UNIQUE KEY results_unique (student_id, course_id, academic_session_id, semester_id);

ALTER TABLE results
  ADD INDEX results_student_lookup (student_id, academic_session_id, semester_id);

ALTER TABLE results
  ADD COLUMN uploaded_by INT UNSIGNED NULL AFTER status,
  ADD CONSTRAINT results_uploaded_by_fk FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL;

-- Matric number is already UNIQUE on students, so lookups by matric
-- are already indexed — nothing more needed there.
