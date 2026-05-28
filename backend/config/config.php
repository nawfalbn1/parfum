<?php
/**
 * Application configuration.
 */

$envFile = __DIR__ . '/../../.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value, " \t\n\r\0\x0B\"'"));
    }
}

define('APP_NAME',    'Fragrance by Nawfal');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/site%20dyali');
define('APP_ENV',     'development'); 

define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');

// Session
define('SESSION_LIFETIME', 60 * 60 * 24 * 7); // 7 days

// Pagination
define('PRODUCTS_PER_PAGE', 12);

// Upload paths
define('UPLOAD_DIR',  __DIR__ . '/../public/uploads/');
define('UPLOAD_URL',  APP_URL . '/public/uploads/');


define('SHIPPING_COST', 30.00);
define('FREE_SHIPPING_THRESHOLD', 500.00);

if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
