Setup and run the Wesley Portal (admin upload + student checker)

1) Database
- Create a MySQL database and import the schema:

  mysql -u root -p < db/schema.sql

- Create an admin user for testing (use a secure password):

  INSERT INTO admins (username, password_hash) VALUES ('admin', 'REPLACE_WITH_PASSWORD_HASH');

  // To generate a password hash in PHP:
  // php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT) . PHP_EOL;"

2) PHP dependencies
- Install composer dependencies from the `wesley-portal` folder:

```powershell
cd "c:\Users\USER\Desktop\WESLEY UNIVERSITY PORTAL\wesley-portal"
composer install
```

This installs `phpoffice/phpspreadsheet` which enables .xlsx parsing for the bulk uploader.

3) Configuration
- Edit `wesley-portal/includes/config.php` to set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` constants.

4) Run locally (development)
- From the project root you can run the built-in PHP server:

```powershell
cd "c:\Users\USER\Desktop\WESLEY UNIVERSITY PORTAL"
php -S localhost:8000 -t wesley-portal
```

- Open `http://localhost:8000/admin/upload-results.html` to sign in and upload spreadsheets.

5) Notes
- The bulk upload endpoint `api/bulk_upload.php` accepts CSV and XLSX files. For XLSX support, ensure composer dependencies are installed.
- The uploader will deduplicate by file hash to avoid processing the same file twice.
- Server-side endpoints require an admin session; use `api/admin_login.php` to authenticate.
- The student lookup uses `api/check_result.php` which is already optimized with an index on the `students.matric` column.

Security and production recommendations
- Run behind HTTPS.
- Use a robust authentication system and secure cookie settings.
- Add rate-limiting and audit logging for uploads.
- Validate and sanitize all inputs before displaying them in the UI to prevent XSS.
