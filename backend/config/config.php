<?php
/**
 * Application Configuration
 * Fragrance by Nawfal – Backend
 */

define('APP_NAME',    'Fragrance by Nawfal');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost:8000');
define('APP_ENV',     'development'); // 'production' in prod

// ── Session ────────────────────────────────────────────────
define('SESSION_LIFETIME', 60 * 60 * 24 * 7); // 7 days

// ── Pagination ──────────────────────────────────────────────
define('PRODUCTS_PER_PAGE', 12);

// ── Upload paths ────────────────────────────────────────────
define('UPLOAD_DIR',  __DIR__ . '/../public/uploads/');
define('UPLOAD_URL',  APP_URL . '/public/uploads/');

// ── Shipping cost (MAD) ─────────────────────────────────────
define('SHIPPING_COST', 30.00);
define('FREE_SHIPPING_THRESHOLD', 500.00);

// ── Error display ───────────────────────────────────────────
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
