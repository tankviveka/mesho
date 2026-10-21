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

    // 0. Optional local override file (free shared hosting such as
    //    InfinityFree, where environment variables are not available).
    //    Create config/db.local.php with:
    //        <?php return ['host' => 'sqlXXX.infinityfree.com',
    //                       'port' => '3306', 'name' => 'if0_12345',
    //                       'user' => 'if0_12345', 'pass' => 'secret'];
    $localFile = __DIR__ . '/db.local.php';
    if (is_file($localFile)) {
        $local = require $localFile;
        if (is_array($local)) {
            foreach (['host', 'port', 'name', 'user', 'pass'] as $key) {
                if (isset($local[$key]) && (string) $local[$key] !== '') {
                    $cfg[$key] = (string) $local[$key];
                }
            }
        }
    }

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
 * Web path of the application root ( '' at the domain root, '/mesho' when
 * the app lives in a sub folder ). Used to build correct links from any
 * script, including the ones inside /database.
 */
function ms_app_base_path(): string
{
    $docRoot = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $appDir  = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');

    if ($docRoot !== '' && $appDir !== '' && str_starts_with($appDir, $docRoot)) {
        return rtrim(substr($appDir, strlen($docRoot)), '/');
    }

    return '';
}

/**
 * Create the demo database if it does not exist yet.
 *
 * On a hosted MySQL (Railway, cPanel, ...) the account usually has the right
 * to create its own database, and the configured name may not exist yet.
 * This makes the app self-healing instead of failing with error 1049.
 */
function ms_ensure_database(): bool
{
    $safeName = preg_replace('/[^A-Za-z0-9_]/', '_', DB_NAME) ?: 'mesho_demo';

    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT),
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $pdo->exec(
            'CREATE DATABASE IF NOT EXISTS `' . $safeName . '` '
            . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

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
        // Error 1049 = "Unknown database". The server answered, so the
        // credentials are right - the database itself is simply missing.
        $isMissingDb = ((int) ($e->errorInfo[1] ?? 0)) === 1049
            || str_contains($e->getMessage(), '1049');

        if ($isMissingDb && ms_ensure_database()) {
            try {
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $retryError) {
                $e = $retryError;
                $isMissingDb = false;
            }
        }

        if (!$pdo instanceof PDO) {
            http_response_code(500);
            ms_render_db_error($e, $isMissingDb ? 'missing-db' : 'connection');
            exit;
        }
    }

    return $pdo;
}

/**
 * Friendly, environment-aware error page (never leaks the password).
 *
 * @param string $reason 'missing-db' | 'missing-tables' | 'connection'
 */
function ms_render_db_error(Throwable $e, string $reason = 'connection'): void
{
    $base  = ms_app_base_path();
    $host  = htmlspecialchars(DB_HOST . ':' . DB_PORT, ENT_QUOTES);
    $db    = htmlspecialchars(DB_NAME, ENT_QUOTES);
    $hosted = ms_env('RAILWAY_ENVIRONMENT', 'RAILWAY_SERVICE_ID', 'MYSQLHOST', 'MYSQL_URL') !== '';
    $installLink = $base . '/database/install.php';

    if ($reason === 'missing-tables') {
        $title = 'Demo data not installed yet';
        $body  = '<p class="lead">The database <code>' . $db . '</code> exists but is empty - '
            . 'the demo tables and sample orders have not been created yet.</p>'
            . '<p>One click creates the tables, the demo users and 1,400 sample orders:</p>'
            . '<a class="btn btn-primary btn-lg" href="' . $installLink . '">'
            . 'Create &amp; seed the demo database</a>';
    } elseif ($reason === 'missing-db') {
        $title = 'Demo database not created yet';
        $body  = '<p class="lead">The MySQL server answered, but the database '
            . '<code>' . $db . '</code> does not exist yet.</p>'
            . '<p>One click creates the database, the tables and 1,400 sample orders:</p>'
            . '<a class="btn btn-primary btn-lg" href="' . $installLink . '">'
            . 'Create &amp; seed the demo database</a>';
    } elseif ($hosted) {
        $title = 'Could not reach the database';
        $body  = '<p class="lead">The app cannot connect to MySQL.</p>'
            . '<p>Check that:</p><ol>'
            . '<li>The MySQL service is running (not crashed / not sleeping).</li>'
            . '<li>These variables exist in your app service:<br>'
            . '<code>MYSQL_URL</code> or <code>MYSQLHOST</code> / <code>MYSQLPORT</code> / '
            . '<code>MYSQLUSER</code> / <code>MYSQLPASSWORD</code> / <code>MYSQLDATABASE</code></li>'
            . '<li>They are set for the <em>same environment</em> as the deployed app.</li>'
            . '</ol>'
            . '<p>Then redeploy and reload this page.</p>';
    } else {
        $title = 'Could not connect to the demo database';
        $body  = '<p>Please make sure:</p><ol>'
            . '<li>MySQL is running in XAMPP (XAMPP Control Panel &rarr; Start <strong>MySQL</strong>).</li>'
            . '<li>The database <code>' . $db . '</code> exists.</li>'
            . '<li>You imported <code>database/schema.sql</code> and <code>database/seed.sql</code> '
            . '(or ran <code>database/install.php</code>).</li></ol>'
            . '<p><a class="btn btn-primary" href="' . $installLink . '">Run the installer</a></p>';
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . $title . '</title>'
        . '<link rel="stylesheet" href="' . $base . '/assets/vendor/bootstrap.min.css"></head>'
        . '<body class="bg-light"><div class="container py-5" style="max-width:760px">'
        . '<div class="alert alert-danger"><h4 class="alert-heading">' . $title . '</h4>'
        . $body
        . '<p class="mb-0 small text-muted">Server: ' . $host . ' &middot; Database: ' . $db
        . '<br>Technical detail: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>'
        . '</div>'
        . '<a class="btn btn-outline-secondary" href="' . $base . '/login.php">Back to login</a>'
        . '</div></body></html>';
}


/**
 * Run a prepared statement and return it.
 *
 * @param array<string|int, mixed> $params
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        // Error 1146 = "Table doesn't exist" - the database is there but the
        // demo tables were never installed. Show the installer instead of a
        // blank 500 error page.
        $code = (int) ($e->errorInfo[1] ?? 0);
        if ($code === 1146 || str_contains($e->getMessage(), '1146')) {
            http_response_code(500);
            ms_render_db_error($e, 'missing-tables');
            exit;
        }
        throw $e;
    }
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
