<?php
/**
 * includes/db.php
 *
 * Single shared PDO connection for the whole app. Include this once per
 * request (it's cheap to include multiple times too — PHP will just skip
 * re-declaring the constants/function below via require_once).
 *
 * Usage from any page:
 *   require_once __DIR__ . '/includes/db.php';
 *   $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
 *   $stmt->execute([$courseId]);
 *
 * Every query in this codebase MUST go through prepare()/execute() with
 * bound parameters — never sprintf/concatenate user input into SQL.
 */

// ---------------------------------------------------------------------
// Connection settings.
// In production, prefer pulling these from environment variables (set in
// your web server / php-fpm pool config) rather than hardcoding secrets
// in a file that lives in the repo. The getenv() calls below fall back to
// local defaults so the app also runs out-of-the-box on a fresh XAMPP/
// local MySQL setup during development.
// ---------------------------------------------------------------------
$dbHost = getenv('BASSIRA_DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('BASSIRA_DB_PORT') ?: '3306';
$dbName = getenv('BASSIRA_DB_NAME') ?: 'bassira_hall';
$dbUser = getenv('BASSIRA_DB_USER') ?: 'bassira_user';
$dbPass = getenv('BASSIRA_DB_PASS') ?: 'awaneconfort';

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

$options = [
    // Throw exceptions on error instead of silently failing or requiring
    // manual error checks after every single query.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

    // Return associative arrays by default ($row['column'], not [0=>...]).
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // Use real prepared statements (not client-side emulation) so bound
    // values are always sent as data, never interpolated into the SQL
    // string by the driver itself.
    PDO::ATTR_EMULATE_PREPARES   => false,

    // Belt-and-suspenders alongside the DSN's charset=utf8mb4: some PHP/
    // MySQL driver combinations ignore the DSN charset silently, so set
    // it explicitly on connection too. utf8mb4 (not utf8) is required —
    // Arabic diacritics (tashkīl) and some Qur'anic characters fall
    // outside plain utf8's 3-byte range and would otherwise get mangled
    // or rejected on insert.
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    // Never leak connection details (host/user/pass) in the error shown
    // to the browser. Log the real exception server-side instead.
    error_log('[Bassira] DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('عذراً، حدث خطأ في الاتصال بالخادم. حاول مرة أخرى لاحقاً.');
}
