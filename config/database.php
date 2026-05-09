<?php
/**
 * Database connection — credentials from environment only (.env).
 *
 * @package SchoolManager
 */

require_once __DIR__ . '/load_env.php';

/**
 * Read env with optional default for non-secret keys only.
 */
function db_env(string $key, string $default = ''): string
{
    if (array_key_exists($key, $_ENV)) {
        return (string)$_ENV[$key];
    }
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return (string)$v;
    }
    return $default;
}

define('DB_HOST', db_env('DB_HOST', 'localhost'));
define('DB_NAME', db_env('DB_NAME', 'school_db'));
define('DB_USER', db_env('DB_USER', 'root'));
define('DB_PASS', db_env('DB_PASS', ''));
define('DB_CHARSET', db_env('DB_CHARSET', 'utf8mb4'));

/**
 * PDO connection singleton.
 */
function getConnection(): PDO
{
    static $conn = null;
    if ($conn instanceof PDO) {
        return $conn;
    }
    $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!is_readable($envPath)) {
        die('Missing .env file. Copy .env.example to .env and set DB_* variables.');
    }
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $conn = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ]);
    } catch (PDOException $e) {
        die('Database connection error. Check .env credentials.');
    }
    return $conn;
}
