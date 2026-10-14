<?php
/**
 * -----------------------------------------------------------------------------
 * Meesho Seller Dashboard - DEMO
 * -----------------------------------------------------------------------------
 * Database configuration + PDO connection helper.
 *
 * IMPORTANT: This project is a DEMO ONLY. It does NOT connect to, sync with or
 * import any real Meesho seller account data. Everything you see is generated
 * sample data stored locally in the MySQL database below.
 *
 * Default XAMPP credentials are used (user: root / no password).
 * You can override them with environment variables if needed.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (!defined('MS_DEMO')) {
    define('MS_DEMO', true);
}

/* ------------------------------------------------------------------ */
/* Database credentials (XAMPP defaults)                               */
/* ------------------------------------------------------------------ */
define('DB_HOST', getenv('MS_DB_HOST') !== false ? getenv('MS_DB_HOST') : '127.0.0.1');
define('DB_PORT', getenv('MS_DB_PORT') !== false ? getenv('MS_DB_PORT') : '3306');
define('DB_NAME', getenv('MS_DB_NAME') !== false ? getenv('MS_DB_NAME') : 'mesho_demo');
define('DB_USER', getenv('MS_DB_USER') !== false ? getenv('MS_DB_USER') : 'root');
define('DB_PASS', getenv('MS_DB_PASS') !== false ? getenv('MS_DB_PASS') : '');

/* ------------------------------------------------------------------ */
/* Application constants                                               */
/* ------------------------------------------------------------------ */
define('APP_NAME', 'Meesho Seller Dashboard');
define('APP_TAGLINE', 'Demo Edition');
define('DEMO_NOTICE', 'DEMO MODE - Sample data only. Not connected to real Meesho data.');
define('ITEMS_PER_PAGE_DEFAULT', 10);
define('ITEMS_PER_PAGE_OPTIONS', [10, 25, 50, 100]);

/* ------------------------------------------------------------------ */
/* PDO connection (singleton)                                          */
/* ------------------------------------------------------------------ */

/**
 * Open (and reuse) a PDO connection to the demo database.
 *
 * All queries in this project use prepared statements - never string
 * concatenation - so that user input can never alter the SQL structure.
 *
 * @throws PDOException when the database is unreachable.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        // Friendly setup guidance instead of leaking driver details.
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Database connection failed</title>'
            . '<link rel="stylesheet" href="assets/vendor/bootstrap.min.css"></head>'
            . '<body class="bg-light"><div class="container py-5"><div class="alert alert-danger">'
            . '<h4 class="alert-heading">Could not connect to the demo database</h4>'
            . '<p class="mb-2">Please make sure:</p><ol class="mb-2">'
            . '<li>MySQL is running in XAMPP (XAMPP Control Panel &rarr; Start <strong>MySQL</strong>).</li>'
            . '<li>The database <code>' . htmlspecialchars(DB_NAME, ENT_QUOTES) . '</code> exists.</li>'
            . '<li>You imported <code>database/schema.sql</code> and <code>database/seed.sql</code> '
            . '(or ran <code>database/install.php</code>).</li></ol>'
            . '<p class="mb-0 small text-muted">Technical detail: '
            . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p></div>'
            . '<a class="btn btn-primary" href="login.php">Back to login</a></div></body></html>';
        exit;
    }

    return $pdo;
}

/**
 * Run a prepared statement and return it.
 *
 * @param array<string|int, mixed> $params
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch a single row (or null).
 *
 * @param array<string|int, mixed> $params
 * @return array<string, mixed>|null
 */
function db_row(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/**
 * Fetch all rows.
 *
 * @param array<string|int, mixed> $params
 * @return array<int, array<string, mixed>>
 */
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/**
 * Fetch a single scalar value.
 *
 * @param array<string|int, mixed> $params
 * @return mixed
 */
function db_var(string $sql, array $params = []): mixed
{
    $value = db_query($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}
