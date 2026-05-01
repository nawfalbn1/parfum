<?php
/**
 * Database Connection (PDO Singleton)
 * Fragrance by Nawfal
 */

class Database
{
    // ── Change these for your server ──────────────────────────
    private static string $host     = '127.0.0.1';
    private static string $dbname   = 'fragrance_nawfal';
    private static string $username = 'root';
    private static string $password = '';
    private static string $charset  = 'utf8mb4';

    private static ?PDO $instance = null;

    /** Returns the singleton PDO instance */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                self::$host,
                self::$dbname,
                self::$charset
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, self::$username, self::$password, $options);
            } catch (PDOException $e) {
                // In production, log this rather than displaying
                http_response_code(500);
                die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
            }
        }

        return self::$instance;
    }

    // Prevent direct instantiation / cloning
    private function __construct() {}
    private function __clone() {}
}
