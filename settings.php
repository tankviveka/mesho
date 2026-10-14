<?php
/**
 * Settings - store profile, dashboard preferences, password and demo data.
 *
 * Every value is written with prepared statements and validated before use.
 */

require_once __DIR__ . '/includes/init.php';
require_login();

/* --------------------------- Validation helpers ------------------------ */

function valid_name(string $value, int $max = 80): ?string
{
    $value = trim($value);
    if ($value === '' || mb_strlen($value) > $max) {
        return null;
    }
    if (!preg_match('/^[\p{L}\p{N}\s\.\,\&\-\(\)\/]+$/u', $value)) {
        return null;
    }
    return $value;
}

function valid_email(string $value): ?string
{
    $value = trim($value);
    if ($value === '' || !filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) > 120) {
        return null;
    }
    return $value;
}

function valid_phone(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (!preg_match('/^[0-9+\-\s]{6,18}$/', $value)) {
        return null;
    }
    return $value;
}

/* ------------------------------ POST actions ---------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    /* ---- 1. Store profile ---- */
    if ($action === 'profile') {
        $store = valid_name((string) ($_POST['store_name'] ?? ''), 60);
        $owner = valid_name((string) ($_POST['owner_name'] ?? ''), 60);
        $email = valid_email((string) ($_POST['store_email'] ?? ''));
        $phone = valid_phone((string) ($_POST['store_phone'] ?? ''));
        $city  = valid_name((string) ($_POST['store_city'] ?? ''), 50);
        $state = valid_name((string) ($_POST['store_state'] ?? ''), 50);
        $gst   = strtoupper(trim((string) ($_POST['gst_no'] ?? '')));

        if ($store === null || $owner === null || $email === null
            || $phone === null || $city === null || $state === null) {
            flash_set('error', 'Profile not saved: please fill every field correctly (letters, numbers and basic punctuation only).');
        } elseif ($gst !== '' && !preg_match('/^[0-9A-Z]{8,15}$/', $gst)) {
            flash_set('error', 'Profile not saved: GST number must be 8-15 letters/digits.');
        } else {
            set_setting('store_name', $store);
            set_setting('owner_name', $owner);
            set_setting('store_email', $email);
            set_setting('store_phone', $phone);
            set_setting('store_city', $city);
            set_setting('store_state', $state);
            set_setting('gst_no', $gst);
            flash_set('success', 'Store profile updated.');
        }
        header('Location: settings.php#profile');
        exit;
    }

    /* ---- 2. Preferences ---- */
    if ($action === 'preferences') {
        $perPage = (int) ($_POST['default_per_page'] ?? ITEMS_PER_PAGE_DEFAULT);
        if (!in_array($perPage, ITEMS_PER_PAGE_OPTIONS, true)) {
            $perPage = ITEMS_PER_PAGE_DEFAULT;
        }
        $banner = isset($_POST['show_demo_banner']) ? '1' : '0';
        $compact = isset($_POST['compact_tables']) ? '1' : '0';

        set_setting('default_per_page', (string) $perPage);
        set_setting('show_demo_banner', $banner);
        set_setting('compact_tables', $compact);
        flash_set('success', 'Dashboard preferences saved.');
        header('Location: settings.php#preferences');
        exit;
    }

    /* ---- 3. Change password ---- */
    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $repeat = (string) ($_POST['repeat_password'] ?? '');

        $user = db_row('SELECT password_hash FROM users WHERE id = ?', [(int) $_SESSION['user_id']]);

        if ($user === null) {
            flash_set('error', 'Account not found. Please log in again.');
            header('Location: logout.php');
            exit;
        }
        if (!password_verify($current, $user['password_hash'])) {
            flash_set('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash_set('error', 'New password must be at least 8 characters long.');
        } elseif ($new !== $repeat) {
            flash_set('error', 'New passwords do not match.');
        } else {
            db_query('UPDATE users SET password_hash = ? WHERE id = ?', [
                password_hash($new, PASSWORD_DEFAULT),
                (int) $_SESSION['user_id'],
            ]);
            session_regenerate_id(true);
            flash_set('success', 'Password changed successfully.');
        }
        header('Location: settings.php#security');
        exit;
    }
}

/* -------------------------------- Data ---------------------------------- */

$profileKeys = ['store_name', 'owner_name', 'store_email', 'store_phone', 'store_city', 'store_state', 'gst_no'];
$profile = [];
foreach ($profileKeys as $key) {
    $profile[$key] = get_setting($key, '');
}

$defaultPerPage = (int) get_setting('default_per_page', (string) ITEMS_PER_PAGE_DEFAULT);
$showBanner = get_setting('show_demo_banner', '1') === '1';
$compactTables = get_setting('compact_tables', '0') === '1';

/* Table statistics (all read from MySQL) */
$stats = db_row(
    'SELECT (SELECT COUNT(*) FROM orders)    AS orders,
            (SELECT COUNT(*) FROM products)  AS products,
            (SELECT COUNT(*) FROM payments)  AS payments,
            (SELECT COUNT(*) FROM users)     AS users'
) ?? ['orders' => 0, 'products' => 0, 'payments' => 0, 'users' => 0];

$dbName = DB_NAME;
$dbUser = DB_USER;
$phpVersion = PHP_VERSION;
$dbVersion = db_var('SELECT VERSION()') ?? 'unknown';

$user = db_row(
    'SELECT name, username, email, role, created_at FROM users WHERE id = ?',
    [(int) $_SESSION['user_id']]
) ?? [];

$pageTitle = 'Settings';
$active = 'settings';
require __DIR__ . '/includes/header.php';
?>

<div class="demo-banner mb-3">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div><strong>DEMO MODE:</strong> these settings only affect this local demo installation.
        Nothing here connects to a real Meesho seller account.</div>
</div>

<div class="row g-3">
    <div class="col-lg-8">

        <?php /* ------------------------- Store profile ------------------- */ ?>
        <div class="card mb-3" id="profile">
            <div class="card-header"><i class="bi bi-shop me-2 text-primary"></i>Store Profile</div>
            <div class="card-body">
                <form method="post" action="settings.php#profile" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="store_name">Store name *</label>
                            <input class="form-control" id="store_name" name="store_name" maxlength="60"
                                   value="<?= e($profile['store_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="owner_name">Owner name *</label>
                            <input class="form-control" id="owner_name" name="owner_name" maxlength="60"
                                   value="<?= e($profile['owner_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="store_email">Email *</label>
                            <input class="form-control" type="email" id="store_email" name="store_email"
                                   maxlength="120" value="<?= e($profile['store_email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="store_phone">Phone</label>
                            <input class="form-control" id="store_phone" name="store_phone" maxlength="18"
                                   value="<?= e($profile['store_phone']) ?>" placeholder="+91 98765 43210">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="store_city">City</label>
                            <input class="form-control" id="store_city" name="store_city" maxlength="50"
                                   value="<?= e($profile['store_city']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="store_state">State</label>
                            <input class="form-control" id="store_state" name="store_state" maxlength="50"
                                   value="<?= e($profile['store_state']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="gst_no">GST number (optional)</label>
                            <input class="form-control" id="gst_no" name="gst_no" maxlength="15"
                                   value="<?= e($profile['gst_no']) ?>" placeholder="22AAAAA0000A1Z5">
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3" type="submit">
                        <i class="bi bi-check2-circle me-1"></i>Save profile
                    </button>
                </form>
            </div>
        </div>

        <?php /* ------------------------ Preferences ---------------------- */ ?>
        <div class="card mb-3" id="preferences">
            <div class="card-header"><i class="bi bi-sliders me-2 text-primary"></i>Dashboard Preferences</div>
            <div class="card-body">
                <form method="post" action="settings.php#preferences">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="preferences">

                    <div class="mb-3" style="max-width:280px;">
                        <label class="form-label" for="default_per_page">Default rows per page</label>
                        <select class="form-select" id="default_per_page" name="default_per_page">
                            <?php foreach (ITEMS_PER_PAGE_OPTIONS as $n): ?>
                                <option value="<?= $n ?>" <?= $defaultPerPage === $n ? 'selected' : '' ?>><?= $n ?> rows</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Applies whenever a table is opened without a "per page" choice.</div>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="show_demo_banner"
                               name="show_demo_banner" value="1" <?= $showBanner ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_demo_banner">Show the DEMO MODE notice on data pages</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="compact_tables"
                               name="compact_tables" value="1" <?= $compactTables ? 'checked' : '' ?>>
                        <label class="form-check-label" for="compact_tables">Compact table rows (less padding)</label>
                    </div>

                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-check2-circle me-1"></i>Save preferences
                    </button>
                </form>
            </div>
        </div>

        <?php /* --------------------------- Security ---------------------- */ ?>
        <div class="card mb-3" id="security">
            <div class="card-header"><i class="bi bi-shield-lock me-2 text-primary"></i>Security</div>
            <div class="card-body">
                <form method="post" action="settings.php#security" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="current_password">Current password</label>
                            <input class="form-control" type="password" id="current_password"
                                   name="current_password" maxlength="200" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="new_password">New password</label>
                            <input class="form-control" type="password" id="new_password"
                                   name="new_password" minlength="8" maxlength="200" required>
                            <div class="form-text">Minimum 8 characters.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="repeat_password">Repeat new password</label>
                            <input class="form-control" type="password" id="repeat_password"
                                   name="repeat_password" minlength="8" maxlength="200" required>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3" type="submit">
                        <i class="bi bi-key me-1"></i>Change password
                    </button>
                </form>
            </div>
        </div>

        <?php /* -------------------------- Demo data ---------------------- */ ?>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-database-exclamation me-2 text-danger"></i>Demo Data</div>
            <div class="card-body">
                <p class="mb-2">
                    The dashboard reads from the local MySQL database <code><?= e($dbName) ?></code>.
                    You can rebuild all sample data at any time &mdash; this drops and recreates the demo
                    tables (orders, products, payments) and re-inserts fresh sample rows.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-danger"
                       href="database/install.php?action=install&amp;confirm=1"
                       data-confirm="Rebuild ALL demo data? This replaces every order, product and payment row.">
                        <i class="bi bi-arrow-repeat me-1"></i>Rebuild demo data
                    </a>
                    <a class="btn btn-light" href="database/install.php">
                        <i class="bi bi-clipboard-data me-1"></i>Installation status
                    </a>
                </div>
                <div class="small text-muted mt-3">
                    Never run this against a database containing real data &mdash; this project is for
                    demonstration purposes only.
                </div>
            </div>
        </div>

    </div>

    <?php /* ------------------------- Sidebar info ------------------------ */ ?>
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person-badge me-2 text-primary"></i>Signed-in account</div>
            <div class="card-body">
                <div class="stat-row"><span class="label">Name</span><span class="value"><?= e($user['name'] ?? '-') ?></span></div>
                <div class="stat-row"><span class="label">Username</span><span class="value"><?= e($user['username'] ?? '-') ?></span></div>
                <div class="stat-row"><span class="label">Email</span><span class="value"><?= e($user['email'] ?? '-') ?></span></div>
                <div class="stat-row"><span class="label">Role</span><span class="value"><?= e($user['role'] ?? '-') ?></span></div>
                <div class="stat-row">
                    <span class="label">Member since</span>
                    <span class="value"><?= !empty($user['created_at']) ? date_fmt((string) $user['created_at']) : '-' ?></span>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-hdd-stack me-2 text-primary"></i>Database statistics</div>
            <div class="card-body">
                <div class="stat-row"><span class="label">Orders rows</span><span class="value"><?= number_format((int) $stats['orders']) ?></span></div>
                <div class="stat-row"><span class="label">Products rows</span><span class="value"><?= number_format((int) $stats['products']) ?></span></div>
                <div class="stat-row"><span class="label">Payments rows</span><span class="value"><?= number_format((int) $stats['payments']) ?></span></div>
                <div class="stat-row"><span class="label">User rows</span><span class="value"><?= number_format((int) $stats['users']) ?></span></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-2 text-primary"></i>Environment</div>
            <div class="card-body">
                <div class="stat-row"><span class="label">PHP version</span><span class="value"><?= e($phpVersion) ?></span></div>
                <div class="stat-row"><span class="label">MySQL / MariaDB</span><span class="value"><?= e($dbVersion) ?></span></div>
                <div class="stat-row"><span class="label">Database</span><span class="value"><?= e($dbName) ?></span></div>
                <div class="stat-row"><span class="label">DB user</span><span class="value"><?= e($dbUser) ?></span></div>
                <div class="stat-row"><span class="label">Mode</span><span class="value"><span class="badge rounded-pill bg-warning text-dark">DEMO MODE</span></span></div>
            </div>
        </div>
    </div>
</div>

<?php if ($compactTables): ?>
<style>.table tbody td { padding: 7px 14px; }</style>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
