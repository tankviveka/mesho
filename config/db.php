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
/* Database credentials                                                */
/* ------------------------------------------------------------------ */
/*
 * This project runs unchanged on local XAMPP *and* on a host such as
 * Railway, where the database is another private service.
 *
 * Resolution order (first match wins for each value):
 *   1. MS_DB_HOST / MS_DB_PORT / MS_DB_NAME / MS_DB_USER / MS_DB_PASS
 *   2. MYSQL_URL or DATABASE_URL  (mysql://user:pass@host:port/db)
 *   3. MYSQLHOST / MYSQLPORT / MYSQLUSER / MYSQLPASSWORD / MYSQLDATABASE
 *      (the variables a Railway MySQL service exposes to your app)
 *   4. Local XAMPP defaults: 127.0.0.1:3306, root, no password, mesho_demo
 */

/** Read the first non-empty environment variable from a list. */
function ms_env(string ...$names): string
{
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false && trim((string) $value) !== '') {
            return trim((string) $value);
        }
    }
    return '';
}

/**
 * Build the database settings array from the environment.
 *
 * @return array{host:string,port:string,name:string,user:string,pass:string,charset:string}
 */
function ms_db_config(): array
{
    $cfg = [
        'host'   => '127.0.0.1',
        'port'   => '3306',
        'name'   => 'mesho_demo',
        'user'   => 'root',
        'pass'   => '',
        'charset' => 'utf8mb4',
    ];

    // 2. Full connection string (MySQL URL style).
    $url = ms_env('MYSQL_URL', 'DATABASE_URL');
    if ($url !== '' && preg_match('#^mysql(2)?://#i', $url) === 1) {
        $parts = parse_url($url);
        if (is_array($parts)) {
            if (isset($parts['host']) && $parts['host'] !== '') {
                $cfg['host'] = (string) $parts['host'];
            }
            if (isset($parts['port'])) {
                $cfg['port'] = (string) $parts['port'];
            }
            if (isset($parts['user'])) {
                $cfg['user'] = rawurldecode((string) $parts['user']);
            }
            if (array_key_exists('pass', $parts)) {
                $cfg['pass'] = rawurldecode((string) $parts['pass']);
            }
            $db = ltrim((string) ($parts['path'] ?? ''), '/');
            if ($db !== '') {
                $cfg['name'] = rawurldecode($db);
            }
        }
    }

    // 3. Individual variables (Railway MySQL service provides these).
    $map = [
        'host' => ['MYSQLHOST', 'MYSQL_HOST'],
        'port' => ['MYSQLPORT', 'MYSQL_PORT'],
        'user' => ['MYSQLUSER', 'MYSQL_USER'],
        'pass' => ['MYSQLPASSWORD', 'MYSQL_PASSWORD'],
        'name' => ['MYSQLDATABASE', 'MYSQL_DATABASE'],
    ];
    foreach ($map as $key => $names) {
        $value = ms_env(...$names);
        if ($value !== '') {
            $cfg[$key] = $value;
        }
    }

    // 1. Explicit MS_DB_* overrides always win.
    $overrides = [
        'host' => 'MS_DB_HOST',
        'port' => 'MS_DB_PORT',
        'name' => 'MS_DB_NAME',
        'user' => 'MS_DB_USER',
        'pass' => 'MS_DB_PASS',
    ];
    foreach ($overrides as $key => $name) {
        $value = ms_env($name);
        if ($value !== '') {
            $cfg[$key] = $value;
        }
    }

    return $cfg;
}

$msDbCfg = ms_db_config();
define('DB_HOST', $msDbCfg['host']);
define('DB_PORT', $msDbCfg['port']);
define('DB_NAME', $msDbCfg['name']);
define('DB_USER', $msDbCfg['user']);
define('DB_PASS', $msDbCfg['pass']);
unset($msDbCfg);

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
