Wesley Portal — PHP/MySQL backend

Quick setup instructions (shared hosting / cPanel / local LAMP):

1. Create a MySQL database and user (note credentials)
2. Import the schema: `wesley-portal/db/schema.sql` into the database
3. Edit `wesley-portal/includes/config.php` and set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
4. Create an admin account (use PHP to generate a bcrypt hash):

   ```php
   <?php
   echo password_hash('your-admin-password', PASSWORD_DEFAULT);
   ```

   Then insert into MySQL:

   INSERT INTO admins (username, password_hash) VALUES ('admin', '<the hash>');

5. Ensure the web server can execute PHP and that the `wesley-portal/api` and `wesley-portal/includes` are accessible.

API endpoints added:
- `api/check_result.php?matric=...` — returns JSON student + semesters + courses
- `api/add_result.php` — POST JSON (requires basic auth for now) to add a semester and courses

Notes & next steps:
- This first pass focuses on secure storage, prepared statements, and a fast lookup by matric (indexed).
- File uploads (PDF/Excel) and advanced admin UI are in the next iteration; see the TODO list in the root for status.
- To compute CGPA across all semesters, implement a backend aggregation (available in `includes/db.php` as helper functions).
