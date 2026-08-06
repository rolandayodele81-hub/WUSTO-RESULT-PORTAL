<?php
// Database configuration - update with your hosting credentials
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'wesley_portal');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('APP_ENV', 'production');
// Matric pattern used for basic validation
define('MATRIC_PATTERN', '/^[A-Z]{2,5}\/20\d{2}\/\d{4}$/i');

// Don't display errors in production
if (APP_ENV !== 'development') {
    ini_set('display_errors', '0');
    error_reporting(0);
}

?>
